<?php 
class ModelExtensionShippingXshippingpro extends Model {
    use OCM\Traits\Front\Address;
    use OCM\Traits\Front\Common_params;
    use OCM\Traits\Front\Product;
    use OCM\Traits\Front\Range_price;
    use OCM\Traits\Front\Crucify;
    use OCM\Traits\Front\Util;
    public function __construct($registry) {
        parent::__construct($registry);
        $this->registry = $registry;
        $this->ocm = ($ocm = $this->registry->get('ocm_front')) ? $ocm : new OCM\Front($this->registry);
        $this->mtype = 'shipping';
        $this->ext_path = 'extension/shipping/xshippingpro';
    }
    function getQuote($address) {
        $this->load->language($this->ext_path);
        $language_id = $this->config->get('config_language_id');
        $address = $this->_replenishAddress($address);
        $compare_with = $this->_getCommonParams($address);
        $only_address_rule = isset($address['only_address_rule']) ? true : false;

        $method_data = array();
        $quote_data = array();
        $sort_data = array(); 
        $heading = $this->ocm->getConfig('xshippingpro_heading', $this->mtype);
        $debug = $this->ocm->getConfig('xshippingpro_debug', $this->mtype);

        $currency_code = isset($this->session->data['currency']) ? $this->session->data['currency'] : $this->config->get('config_currency');

        $_vweight_cache = array();
        $debugging = array();
        $method_level_group = false;
       
        $xshippings = $this->getShippings();
        $xmethods = $xshippings['xmethods'];
        $xmeta = $xshippings['xmeta'];

        $geo_ids = array();
        if ($xmeta['geo']) {
            $geo_rows = $this->db->query("SELECT geo_zone_id FROM " . DB_PREFIX . "zone_to_geo_zone WHERE country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')")->rows; 
            foreach ($geo_rows as $geo_row) {
                $geo_ids[] = $geo_row['geo_zone_id'];
            }
        }
        $cart_products = $this->cart->getProducts();
        $_cart_data =  $this->getProductProfile($cart_products, $xmeta);
        $_cart_data = $this->fixRounding($_cart_data);
        $compare_with['geo'] = $geo_ids;

        foreach($xmethods as $xshippingpro) {
            $rules = $xshippingpro['rules'];
            $rates = $xshippingpro['rates'];
            $tab_id = $xshippingpro['tab_id'];
            $debugging_message = array();

            $alive_or_dead = $this->_crucify($rules, $compare_with, false);
            if (!$alive_or_dead['status']) {
                $debugging_message = $alive_or_dead['debugging'];
                $debugging[] = array('name' => $xshippingpro['display'],'filter' => $debugging_message,'index' => $tab_id);
            } else {
                $status = true;
                $cost = 0;
                $percent_of = $_cart_data['total'];
                if ($rates['type'] == 'flat') {
                    $cost = $rates['percent'] ? ($rates['value'] * $percent_of) : $rates['value'];
                }
                else {
                    $target_value = $_cart_data[$rates['type']];
                    $price_result = $this->getPrice($rates, $target_value, $percent_of);
                    if (!$price_result['status']) {
                        $debugging_message[]='Shipping By - '.$rates['type'].' ('.$target_value.')';
                        $status = false;
                    } else {
                        $cost = $price_result['cost'];
                    }
                }
                if (!$status) {
                    $debugging[] = array('name' => $xshippingpro['display'],'filter' => $debugging_message,'index' => $tab_id);
                }
                if ($status) { 
                    $quote_data['xshippingpro'.$tab_id] = array(
                        'code'         => 'xshippingpro'.'.xshippingpro'.$tab_id,
                        'title'        => $xshippingpro['name'][$language_id],
                        'display'      => $xshippingpro['display'],
                        'cost'         => $cost,
                        'sort_order'   => $xshippingpro['sort_order'],
                        'tax_class_id' => $xshippingpro['tax_class_id'],
                        'text'         => $this->currency->format($this->tax->calculate($cost, $xshippingpro['tax_class_id'], $this->config->get('config_tax')),$currency_code)
                    );
                }
            } 
        }
        /*Sorting final methods */
        $sort_order = array();
        foreach ($quote_data as $key => $value) {
           $sort_order[$key] = $value['sort_order'];
        }
        array_multisort($sort_order, SORT_ASC, $quote_data);
        $heading = isset($heading[$language_id])?$heading[$language_id] : '';
        $method_data = array(
            'code'       => 'xshippingpro',
            'title'      => $heading,
            'quote'      => $quote_data,
            'sort_order' => $this->ocm->getConfig('xshippingpro_sort_order', $this->mtype),
            'error'      => false
        );
        if ($debug) {
            $this->ocm->writeLog($debugging, 'xshippingpro');
        }
        return $quote_data ? $method_data : array();
    }
    private function getShippings() {
        $xshippingpro = $this->cache->get('ocm.xshippingpro');
        if (!$xshippingpro) {
            $language_id = $this->config->get('config_language_id');
            $xmethods = array();
            $xmeta = array(
                'grand' => false,
                'coupon' => false,
                'geo' => false,
                'category_query' => false,
                'product_query' => false,
                'payment_rule' => false,
                'distance' => false
            );
            $xshippingpro_rows = $this->db->query("SELECT * FROM `" . DB_PREFIX . "xshippingpro` order by `sort_order` asc")->rows;
            foreach($xshippingpro_rows as $xshippingpro_row) {
                $method_data = $xshippingpro_row['method_data'];
                $method_data = json_decode($method_data, true);
                /* cache only valid shipping */
                if ($method_data && is_array($method_data) && $method_data['status']) {
                    $method_data =  $this->_resetEmptyRule($method_data);
                    $rules = $this->_findValidRules($method_data);
                    $rates = $this->_findRawRate($method_data);
                    
                    if ($method_data['geo_zone_all'] != 1) {
                        $xmeta['geo'] = true;
                    }
                    $have_product_specified = false;
                    $xmethods[] = array(
                       'tab_id' => (int)$xshippingpro_row['tab_id'],
                       'name' => $method_data['name'],
                       'display' => $method_data['display'],
                       'rules' => $rules,
                       'rates' => $rates,
                       'tax_class_id' => (int)$method_data['tax_class_id'],
                       'sort_order' => (int)$method_data['sort_order']
                    );
                }
            }
            $xshippingpro = array('xmeta' => $xmeta, 'xmethods' => $xmethods);
            $this->cache->set('ocm.xshippingpro', $xshippingpro);
        }
        return $xshippingpro;
   }

    private function _resetEmptyRule($data) {
        $rules = array(
            'store' => 'store_all',
            'geo_zone' => 'geo_zone_all',
        );
        
        foreach ($rules as $key => $value) {
            if (!isset($data[$value])) {
                $data[$value] = '';
            }
            if (!isset($data[$key]) || !$data[$key]) {
                $data[$value] = 1;
            }
        }
        /* reset cost params  */ 
        if (!isset($data['ranges'])) $data['ranges'] = array();
        /* checkboxes */

        /* Reset other */
        if (!isset($data['name']) || !is_array($data['name'])) $data['name']=array();
        if (empty($data['display'])) $data['display'] = 'Untitled Item';
        return $data;
    }
    private function _findValidRules($data) {
        $rules = array();
        if ($data['store_all'] != 1) {
            $rules['store'] = array(
                'type' => 'in_array',
                'product_rule' => false,
                'address_rule' => false,
                'value' => $data['store'],
                'compare_with' => 'store_id',
                'false_value' => false
            );
        }
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
        } else {
           $ranges = array();
           foreach($data['ranges'] as $range) {
               $start = (float)$range['start'];
               $end = (float)$range['end'];
               $cost = trim(trim($range['cost']), '-');
               $block = (float)$range['block'];
               $partial = (int)$range['partial'];
               $product_id = (int)$range['product_id'];
               $type = $range['type'];
               if (substr($cost, -1) == '%') {
                    $cost = rtrim($cost,'%');
                    $percent = true;
                    $value = (float)$cost / 100;
                } else {
                    $percent = false;
                    $value = (float)$cost;
                }
                $ranges[] = array('start' => round($start, 8), 'end' => round($end, 8), 'percent' => $percent, 'value' => $value, 'block' => 0, 'partial' => 0);
            }
            $rates['ranges'] = $ranges;
        }
        return $rates;
    }
    public function getScript() {
        return '';
    }
}