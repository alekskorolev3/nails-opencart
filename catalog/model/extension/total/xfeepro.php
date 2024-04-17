<?php 
class ModelExtensionTotalXfeepro extends Model {
    use OCM\Traits\Front\Address;
    use OCM\Traits\Front\Common_params;
    use OCM\Traits\Front\Product;
    use OCM\Traits\Front\Crucify;
    use OCM\Traits\Front\Util;
    private $mtype;
    private $ext_path;
    public function __construct($registry) {
        parent::__construct($registry);
        $this->registry = $registry;
        $this->ocm = ($ocm = $this->registry->get('ocm_front')) ? $ocm : new OCM\Front($this->registry);
        $this->mtype = 'total';
        $this->ext_path = 'extension/total/xfeepro';
    }
    public function getTotal($total) {
        $default_order = (int)$this->ocm->getConfig('sub_total_sort_order', 'total') + 1;
        $_totals = $this->getXfeeTotal($total['totals'], $total['total'], $total['taxes']);
        if ($_totals)  {
            foreach($_totals as $single) {
                /* Calculating tax/vat */
                if ($single['tax_class_id']) {
                    $tax_rates = $this->tax->getRates($single['cost'], $single['tax_class_id']);
                    foreach ($tax_rates as $tax_rate) {
                        if (!isset($total['taxes'][$tax_rate['tax_rate_id']])) {
                            $total['taxes'][$tax_rate['tax_rate_id']] = 0; // initialize 
                        }
                        $total['taxes'][$tax_rate['tax_rate_id']] += $tax_rate['amount'];
                    }
                }
                $total['total'] += $single['cost'];
                /* End of tax*/ 
                $total['totals'][] = array( 
                    'code'       => 'xfeepro', 
                    'xcode'      =>  $single['code'],
                    'title'      =>  $single['title'],
                    'value'      =>  $single['cost'],
                    'sort_order' => !$single['sort_order'] ? $default_order : (int)$single['sort_order']
                );
            }
        }
    }
    private function getXfeeTotal($totals, $total, $taxes) {
        $this->load->language($this->ext_path);
        $language_id = $this->config->get('config_language_id');
        $address = $this->_replenishAddress();
        $compare_with = $this->_getCommonParams($address);
        $method_data = array();
        $quote_data = array();
        $sort_data = array(); 
        $xfeepro_debug = $this->ocm->getConfig('xfeepro_debug', $this->mtype);
        $currency_code = isset($this->session->data['currency']) ? $this->session->data['currency'] : $this->config->get('config_currency');
        $debugging = array();
        $xfees = $this->getFees();
        $xmethods = $xfees['xmethods'];
        $xmeta = $xfees['xmeta'];
        $cart_products = $this->cart->getProducts();
        $_cart_data =  $this->getProductProfile($cart_products, $xmeta);
        if (!$_cart_data['sub']) return array();
        $_cart_data = $this->fixRounding($_cart_data);
        $geo_ids = array();
        $geo_rows = $this->db->query("SELECT geo_zone_id FROM " . DB_PREFIX . "zone_to_geo_zone WHERE country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')")->rows; 
        foreach ($geo_rows as $geo_row) {
            $geo_ids[] = $geo_row['geo_zone_id'];
        }
        $compare_with['products'] = $_cart_data['products'];
        $compare_with['geo'] = $geo_ids;
        $compare_with['product'] = $_cart_data['product'];
        $compare_with['category'] = $_cart_data['category'];
        $compare_with['manufacturer'] = $_cart_data['manufacturer'];
        $compare_with['option'] = $_cart_data['option'];
        $compare_with['location'] = $_cart_data['location'];
        $compare_with['total'] = $_cart_data['total'];
        $compare_with['weight'] = $_cart_data['weight'];
        $compare_with['quantity'] = $_cart_data['quantity'];
        foreach($xmethods as $xfeepro) {
            $rules = $xfeepro['rules'];
            $rates = $xfeepro['rates'];
            $tab_id = $xfeepro['tab_id'];
            $debugging_message = array();
            /* if shipping_base is found on shipping_methods list, consider that */
            if (isset($rules['shipping']) && in_array($compare_with['shipping_base'], $rules['shipping']['value'])) {
                $compare_with['shipping_method'] = $compare_with['shipping_base'];
            }
            $alive_or_dead = $this->_crucify($rules, $compare_with, false);
            if (!$alive_or_dead['status']) {
                $debugging_message = $alive_or_dead['debugging'];
                $debugging[] = array('name' => $xfeepro['display'],'filter' => $debugging_message,'index' => $tab_id);
            } else {
                $status = true;
                $cost = 0;
                $percent_of = $_cart_data['total'];
                $cost = $rates['percent'] ? ($rates['value'] * $percent_of) : $rates['value'];
                if (!$status) {
                   $debugging[] = array('name' => $xfeepro['display'],'filter' => $debugging_message,'index' => $tab_id);
                }
                if ($status) {
                    $quote_data['xfeepro'.$tab_id] = array(
                        'code'         => 'xfeepro'.$tab_id,
                        'tab_id'       => $tab_id,
                        'title'        => $xfeepro['name'][$language_id],
                        'display'      => $xfeepro['display'],
                        'cost'         => $cost,
                        'sort_order'   => $xfeepro['sort_order'],
                        'tax_class_id' => $xfeepro['tax_class_id']
                    );
                }
            } 
        }
        if ($xfeepro_debug) {
           $this->ocm->writeLog($debugging, 'xfeepro');
        }
       /*Sorting final method*/
        $sort_order = array();
        foreach ($quote_data as $key => $value) {
            $sort_order[$key] = $value['sort_order'];
        }
        array_multisort($sort_order, SORT_ASC, $quote_data);
        return $quote_data;
    }
    private function getFees() {
        $xfeepro = $this->cache->get('ocm.xfeepro');
        if (!$xfeepro) {
            $language_id = $this->config->get('config_language_id');
            $xmethods = array();
            $xmeta = array(
                'geo' => true,
                'category_query' => false,
                'product_query' => false,
                'distance' => false
            );
            $xfeepro_rows = $this->db->query("SELECT * FROM `" . DB_PREFIX . "xfeepro` order by `sort_order` asc")->rows;
            foreach($xfeepro_rows as $xfeepro_row) {
                $method_data = $xfeepro_row['method_data'];
                $method_data = json_decode($method_data, true);
                /* cache only valid shipping */
                if ($method_data && is_array($method_data) && $method_data['status']) {
                    $method_data =  $this->_resetEmptyRule($method_data);
                    $rules = $this->_findValidRules($method_data);
                    $rates = $this->_findRawRate($method_data);
                    $xmethods[] = array(
                       'tab_id' => (int)$xfeepro_row['tab_id'],
                       'name' => $method_data['name'],
                       'display' => $method_data['display'],
                       'rules' => $rules,
                       'rates' => $rates,
                       'tax_class_id' => (int)$method_data['tax_class_id'],
                       'sort_order' => (int)$method_data['sort_order']
                    );
                }
            }
            $xfeepro = array('xmeta' => $xmeta, 'xmethods' => $xmethods);
            $this->cache->set('ocm.xfeepro', $xfeepro);
        }
        return $xfeepro;
    }
    private function _resetEmptyRule($data) {
        $rules = array(
            'geo_zone' => 'geo_zone_all',
            'payment' => 'payment_all',
            'shipping'  => 'shipping_all'
        );
        foreach ($rules as $key => $value) {
            if (!isset($data[$value])) {
                $data[$value] = '';
            }
            if (!isset($data[$key]) || !$data[$key]) {
                $data[$value] = 1;
            }
            /* make empty product entry if all is selected */
            if ($data[$value] < 2 && in_array($key, array('product_category', 'product_product', 'product_option', 'manufacturer', 'location'))) {
                $data[$key] = array();
            }
        }
        $shipping = array();
        if (isset($data['shipping']) && is_array($data['shipping'])) {
            foreach($data['shipping'] as $method) {
                $shipping[] = $method;
                $shipping[] = $method .'.'. $method;
                /* for usps */
                if (strpos($method,'international_') !== false) {
                    $shipping[] = str_replace('international_','',$method);
                }
                if (strpos($method,'domestic_') !== false) {
                    $shipping[] = str_replace('domestic_','',$method);
                }
            }
            $data['shipping'] = $shipping;
        }
        if (!isset($data['name']) || !is_array($data['name'])) $data['name']=array();
        return $data;
    }
    private function _findValidRules($data) {
        $rules = array();
        if ($data['geo_zone_all'] != 1) {
            $rules['geo_zone'] = array(
                'type' => 'intersect',
                'product_rule' => false,
                'address_rule' => true,
                'value' => $data['geo_zone'],
                'compare_with' => 'geo',
                'false_value' => false
            );
        }
        if ($data['payment_all'] != 1) {
            $rules['payment'] = array(
                'type' => 'in_array',
                'product_rule' => false,
                'address_rule' => false,
                'value' => $data['payment'],
                'compare_with' => 'payment_method',
                'false_value' => false
            );
        }
        if ($data['shipping_all'] != 1) {
            $rules['shipping'] = array(
                'type' => 'in_array',
                'product_rule' => false,
                'address_rule' => false,
                'value' => $data['shipping'],
                'compare_with' => 'shipping_method',
                'false_value' => false
            );
        }
        return $rules;
    }
    private function _findRawRate($data) {
        $operators= array('+','-','/','*');
        $rates = array();
        $rates['type'] = $data['rate_type'];
        /* Shipping Cost */
        if ($data['rate_type'] == 'flat') {
            $cost = trim($data['cost']);
            if (substr($cost, -1) == '%') {
                $cost = rtrim($cost,'%');
                $rates['percent'] = true;
                $rates['value'] = (float)$cost / 100;
            } else {
                $rates['percent'] = false;
                $rates['value'] = (float)$cost;
            }
        }
        return $rates;
    }
}