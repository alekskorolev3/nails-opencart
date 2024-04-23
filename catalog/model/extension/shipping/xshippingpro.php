<?php 
class ModelExtensionShippingXshippingpro extends Model {
    private $ext_path;
    private $mtype;
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

        $xshippingpro_heading = $this->ocm->getConfig('xshippingpro_heading', $this->mtype);
        $xshippingpro_group = $this->ocm->getConfig('xshippingpro_group', $this->mtype);
        $xshippingpro_group_limit = $this->ocm->getConfig('xshippingpro_group_limit', $this->mtype);
        $xshippingpro_sub_group = $this->ocm->getConfig('xshippingpro_sub_group', $this->mtype);
        $xshippingpro_sub_group_limit = $this->ocm->getConfig('xshippingpro_sub_group_limit', $this->mtype);
        $xshippingpro_sub_group_name = $this->ocm->getConfig('xshippingpro_sub_group_name', $this->mtype);
        $xshippingpro_sub_group_desc = $this->ocm->getConfig('xshippingpro_sub_group_desc', $this->mtype);
        $xshippingpro_debug = $this->ocm->getConfig('xshippingpro_debug', $this->mtype);
        $xshippingpro_group = $xshippingpro_group ? $xshippingpro_group : 'no_group';
        $xshippingpro_group_limit = $xshippingpro_group_limit ? (int)$xshippingpro_group_limit : 1;
        $xshippingpro_sub_group = $xshippingpro_sub_group ? $xshippingpro_sub_group : array();
        $xshippingpro_sub_group_limit = $xshippingpro_sub_group_limit ?$xshippingpro_sub_group_limit : array();
        $xshippingpro_sub_group_name = $xshippingpro_sub_group_name ? $xshippingpro_sub_group_name : array();
        $xshippingpro_sub_group_desc = $xshippingpro_sub_group_desc ? $xshippingpro_sub_group_desc : array();

        $xshippingpro_sorting = $this->ocm->getConfig('xshippingpro_sorting', $this->mtype);
        $xshippingpro_sorting = ($xshippingpro_sorting)?(int)$xshippingpro_sorting:1;

        $currency_code = isset($this->session->data['currency']) ? $this->session->data['currency'] : $this->config->get('config_currency');

        $_vweight_cache = array();
        $debugging = array();
        $method_level_group = false;
        $hiddenMethods = array();
        $hiddenInactiveMethods = array();
        $sub_options = array();
        $shipping_error = false;

        $xshippings = $this->getShippings();

        $xmethods = $xshippings['xmethods'];
        $xmeta = $xshippings['xmeta'];

        $cart_products = $this->getProducts();
        $_cart_data =  $this->getProductProfile($cart_products, $xmeta);
        $_xtaxes = $_cart_data['tax_data'];
        
        if ($xmeta['grand'] || ($xmeta['coupon'] && $compare_with['coupon_code'])) {
            $xtotals = $this->ocm->getTotals($_xtaxes);
            $_shipping = 0;
            foreach ($xtotals['totals'] as $single) {
                if ($single['code'] == 'coupon') {
                    $_cart_data['coupon'] = $single['value'];
                }
                if ($single['code'] == 'reward') {
                    $_cart_data['reward'] = $single['value'];
                }
                if (isset($single['xcode']) && $single['xcode']) {
                    $_cart_data['xfeepro'][$single['xcode']] = $single['value'];
                }
                if ($single['code'] == 'shipping') {
                    $_shipping = $single['value'];
                }
            }
            $_cart_data['grand'] = $xtotals['total'] - $_shipping;
            $_cart_data['grand_shipping'] = $xtotals['_before_shipping'];
        }

        $geo_ids = array();
        if ($xmeta['geo']) {
            $geo_rows = $this->db->query("SELECT geo_zone_id FROM " . DB_PREFIX . "zone_to_geo_zone WHERE country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')")->rows; 
            foreach ($geo_rows as $geo_row) {
                $geo_ids[] = $geo_row['geo_zone_id'];
            }
        }

        if ($xmeta['distance']) {
            $zone_row = $this->db->query("SELECT name FROM " . DB_PREFIX . "zone WHERE zone_id = '" . (int)$address['zone_id'] . "'")->row;
            $dest = (isset($address['address_1']) && $address['address_1']) ? $address['address_1'] : '';
            $dest .= $address['city'] ? ' '.$address['city'] : '';
            $dest .= $address['postcode'] ? ' '.$address['postcode'] : '';
            $dest .= ($zone_row && $zone_row['name']) ? ' '.$zone_row['name'] : '';
            $_cart_data['distance'] = $this->getDistance($dest);
            $address['zoneName'] = $zone_row['name'];
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
        if ($xmeta['payment_rule'] && !$compare_with['payment_method']) {
            $compare_with['payment_method'] = $this->getDefaultPaymentMethod($address, $_cart_data['total']);
        }

        foreach($xmethods as $xshippingpro) {
            $rules = $xshippingpro['rules'];
            $rates = $xshippingpro['rates'];
            $tab_id = $xshippingpro['tab_id'];
            $product_or = $xshippingpro['product_or'];
            $ingore_product_rule = $xshippingpro['ingore_product_rule'];
            $debugging_message = array();

            /* If products were assigned to multiple categories and cateogry rule is 4, 6 and 7, 
              re-calcaluate method categories */
            if ($_cart_data['multi_category']
                && isset($rules['category'])
                && ($rules['category']['rule_type'] == 4
                    || $rules['category']['rule_type'] == 6 
                    || $rules['category']['rule_type'] == 7)) {

                $rule_categories = $rules['category']['value'];
                $method_categories = array();
                $exclude_categories = array();
                foreach($_cart_data['products'] as $product) {
                    if ($this->array_intersect_faster($rule_categories, $product['category'])) {
                        $method_categories = array_merge($method_categories, $product['category']); 
                    } else {
                        $exclude_categories = array_merge($exclude_categories, $product['category']);  
                    }
                }
                $method_categories = array_unique($method_categories);
                $method_categories = array_diff($method_categories, $exclude_categories); 
                $rules['category']['value'] = $method_categories ? $method_categories : $rule_categories;
            }

            $_cart_data['dimensional'] = 0;
            $_cart_data['volumetric'] = 0;

            $alive_or_dead = $this->_crucify($rules, $compare_with, $product_or, $ingore_product_rule, $only_address_rule);
            if (!$alive_or_dead['status']) {
                if ($xshippingpro['need_inactive_hide_method']) {
                    $hiddenInactiveMethods[$tab_id] = array(
                        'hide' => $xshippingpro['hide_inactive'],
                        'display' => $xshippingpro['display']
                    );
                }
                $debugging_message = $alive_or_dead['debugging'];
                $debugging[] = array('name' => $xshippingpro['display'],'filter' => $debugging_message,'index' => $tab_id);
            } else {
                $status = true;
                $applicable_cart = $this->_getApplicableProducts($rules, $_cart_data);

                if ($rates['type'] == 'dimensional' || $rates['type'] ==  'volumetric') {
                    $cache_key = (int)$rates['factor'].'_'.(int)$rates['overrule'];
                    if (isset($_vweight_cache[$cache_key]) && $_vweight_cache[$cache_key]) {
                        $vweight = $_vweight_cache[$cache_key];
                    } else {
                        $vweight = $this->_calVirtualWeight($_cart_data['products'], $rates['factor'], $rates['overrule']);
                        $_vweight_cache[$cache_key] = $vweight;
                    }
                    $_cart_data['dimensional'] = $vweight['dimensional'];
                    $_cart_data['volumetric'] = $vweight['volumetric'];
                    $_cart_data['product_dimensional'] = $vweight['product_dimensional'];
                    $_cart_data['product_volumetric'] = $vweight['product_volumetric'];
                }

                /* Calculate method wise data if needed*/
                $need_specified = ($xshippingpro['have_product_specified'] && ($xshippingpro['method_specific'] || ($rates['type'] == 'equation' && $rates['equation_specified_param'])));
                $method_specific_data = $this->_getMethodSpecificData($need_specified, $rules, $applicable_cart, $_cart_data, $product_or);

                $cost = 0;
                $percent_of = $method_specific_data[$rates['percent_of']];
                $equation = $this->ocm->html_decode($rates['equation']);
                $iterate_all = preg_match('/{product\w+}/', $equation);

                if ($rates['type'] == 'flat') {
                    $cost = $rates['percent'] ? ($rates['value'] * $percent_of) : $rates['value'];
                }
                else if ($rates['type'] == 'product') {
                    $products = $rates['ranges'];
                    foreach ($method_specific_data['products'] as $product) {
                        $rate = isset($products[$product['product_id']]) ? $products[$product['product_id']] : $rates['additional'];
                        if ($rate) {
                            $no_of_blocks = 1;
                            if ($rate['block']) {
                                $block_type = isset($rate['type']) ? $rate['type'] : 'quantity';
                                $target_value = $rates['cart_adjust'] ? $this->adjustValue($rates['cart_adjust'], $product[$block_type], $product[$block_type]) : $product[$block_type];
                                $no_of_blocks = floor($target_value / $rate['block']);
                            }
                            $cost += ($rate['percent'] ? ($rate['value'] * $product['price']) : $rate['value']) * $no_of_blocks;
                        }
                    }
                }
                else if ($rates['type'] == 'equation' && $iterate_all) {
                    $iteration_result = $this->iterateEquation($equation, 'range', $rates, $method_specific_data, $_cart_data, $quote_data, $percent_of);
                    if ($iteration_result === false) {
                        $debugging_message[] = 'Shipping By - '.$rates['type'].' (iteration Over Products nothing matched)';
                        $status = false;
                    } else {
                        $cost = $iteration_result;
                    }
                }
                else {
                    if ($rates['type'] == 'equation') {
                        $method_specific_data['equation'] = $this->getEquationValue($equation, $_cart_data, $method_specific_data, $quote_data, $percent_of);
                    }
                    $target_value = $method_specific_data[$rates['type']];
                    $target_value = $rates['cart_adjust'] ? $this->adjustValue($rates['cart_adjust'], $percent_of, $target_value) : $target_value;
                    $price_result = $this->getPrice($rates, $target_value, $percent_of);
                    $_equation_check = $rates['type'] == 'equation' ? false : !!$rates['equation'];
                    if (!$price_result['status'] && !$_equation_check) {
                        $debugging_message[] ='Shipping By - '.$rates['type'].' ('.$target_value.')';
                        $status = false;
                    } else {
                        $cost = $rates['final'] == 'single' ? $price_result['cost'] : $price_result['cumulative'];
                    }
                }
                
                /* Price adjustment Start */
                $modifier_amount = 0;
                if ($rates['price_adjust']) {
                    /* Update percent of with shipping */
                    $method_specific_data['sub_shipping'] = $method_specific_data['sub'] + $cost;
                    $method_specific_data['total_shipping'] = $method_specific_data['total'] + $cost;
                    $method_specific_data['shipping'] = $cost;
                    $percent_of = $method_specific_data[$rates['percent_of']];

                    if (isset($rates['price_adjust']['min'])) {
                        $min = $rates['price_adjust']['min'];
                        $min_amount = $min['percent'] ? ($min['value'] * $percent_of) : $min['value'];
                        $cost = $min_amount > $cost ? $min_amount : $cost;
                    }
                    if (isset($rates['price_adjust']['max'])) {
                        $max = $rates['price_adjust']['max'];
                        $max_amount = $max['percent'] ? ($max['value'] * $percent_of) : $max['value'];
                        $cost = $max_amount < $cost ? $max_amount : $cost;
                    }
                    if (isset($rates['price_adjust']['modifier'])) {
                        $modifier = $rates['price_adjust']['modifier'];
                        $modifier_amount = $modifier['percent'] ? ($modifier['value'] * $percent_of) : $modifier['value'];
                        $cost = $this->tiniestCalculator($cost, $modifier_amount, $modifier['operator']);
                    }
                }

                /* If method specified was not true but equation defined with method specific placeholders, let calculate method specifed values if it was not done earlier  */
                if ($rates['equation']
                    && $xshippingpro['have_product_specified']
                    && $rates['equation_specified_param']
                    && !$need_specified) {
                     $method_specific_data = $this->_getMethodSpecificData(true, $rules, $applicable_cart, $_cart_data, $product_or);
                }
                if ($rates['equation'] && $rates['type'] != 'equation') {
                    $method_specific_data['shipping'] = $cost;
                    $percent_of = $method_specific_data[$rates['percent_of']];

                    if (preg_match('/{anyProduct\w+}/', $equation)) {
                        $iteration_type = strpos($equation, '{shipping}') === false ? 'single' : 'multiple';
                        $iteration_result = $this->iterateEquation($equation, $iteration_type, $rates, $method_specific_data, $_cart_data, $quote_data, $percent_of, $cost, $modifier_amount);
                        $cost = $iteration_result === false ? -1 : $cost;
                    } else {
                        $cost = $this->getEquationValue($equation, $_cart_data, $method_specific_data, $quote_data, $percent_of, $cost, $modifier_amount);
                        if ($cost < 0 && $xshippingpro['equation_neg']) {
                            $cost = 0;
                        }
                        // Cost should negetive so that method get failed as price range return false 
                        if (isset($price_result) && !$price_result['status'] && !$cost) {
                            $cost = -1;
                        }
                    }
                    if ($cost < 0) {
                        $status = false; 
                        $debugging_message[] = 'Final Equation  (Return '.$cost.')';
                    }
                }
                /*Ended rate cal*/

                if(!isset($xshippingpro['display'])) $xshippingpro['display'] = '';
                if (!$xshippingpro['display']) {
                   $xshippingpro['display'] = isset($xshippingpro['name'][$language_id]) ? isset($xshippingpro['name'][$language_id]) : '';
                }
                if (!isset($xshippingpro['name'][$language_id]) || !$xshippingpro['name'][$language_id]) {
                   $xshippingpro['name'][$language_id] = 'Untitled Method';
                }

                if (!$status) {
                   $debugging[] = array('name' => $xshippingpro['display'],'filter' => $debugging_message,'index' => $tab_id);
                }

                if ($xshippingpro['inc_weight'] == 1 && $_cart_data['weight'] > 0) {
                    $xshippingpro['name'][$language_id] .= ' ('.$this->weight->format($_cart_data['weight'], $this->config->get('config_weight_class_id'), $this->language->get('decimal_point'), $this->language->get('thousand_point')).')';
                }

                if (intval($xshippingpro['group'])) {
                   $method_level_group = true;
                }

                /* cache for inactive hide */
                if (!$status) { 
                    if ($xshippingpro['need_inactive_hide_method']) {
                        $hiddenInactiveMethods[$tab_id] = array(
                            'hide' => $xshippingpro['hide_inactive'],
                            'display' => $xshippingpro['display']
                        );
                    }
                }

               if ($status) { 
                    if ($xshippingpro['disable']) {
                        $shipping_error = (isset($xshippingpro['error'][$language_id]) && $xshippingpro['error'][$language_id]) ? $this->ocm->html_decode($xshippingpro['error'][$language_id]) : '';
                        if ($shipping_error) {
                            $shipping_error = $this->getFormattedError($shipping_error, $_cart_data['products'], $address, $applicable_cart, $rules);
                            break;
                        }
                    }

                    if ($xshippingpro['need_hide_method']) {
                         $hiddenMethods[$tab_id] = array(
                            'hide' => $xshippingpro['hide'],
                            'display' => $xshippingpro['display']
                          );
                    }
                    if ($xshippingpro['sub_options']) {
                        $sub_options[$tab_id] = $xshippingpro['sub_options'];
                    }
                    $text = $xshippingpro['exc_vat'] ? $this->currency->format($cost,$currency_code) : $this->currency->format($this->tax->calculate($cost, $xshippingpro['tax_class_id'], $this->config->get('config_tax')),$currency_code);
                    $mask = isset($xshippingpro['mask'][$language_id]) ? $this->ocm->html_decode($xshippingpro['mask'][$language_id]) : '';

                    $quote_data['xshippingpro'.$tab_id] = array(
                        'code'         => 'xshippingpro'.'.xshippingpro'.$tab_id,
                        'tab_id'       => $tab_id,
                        'fo'           => $xshippingpro['free_option'],
                        'xkey'         => 'xshippingpro'.$tab_id,
                        'title'        => $xshippingpro['name'][$language_id],
                        'display'      => $xshippingpro['display'],
                        'cost'         => $cost,
                        'group'        => $xshippingpro['group'],
                        'sort_order'   => $xshippingpro['sort_order'],
                        'tax_class_id' => $xshippingpro['tax_class_id'],
                        'exc_vat'      => $xshippingpro['exc_vat'],
                        'text'         => $mask ? $mask : $text
                    );
                }
            } 
        }

        /* Hide methods from hide option*/
        $quote_data = $this->hideMethodsOnActive($quote_data, $hiddenMethods, $debugging);
        $quote_data = $this->hideMethodsOnInactive($quote_data, $hiddenInactiveMethods, $debugging);

        /* Finding sub grouping Or method level grouping  */
        if ($method_level_group) { 
            $grouping_methods = array();
            foreach($quote_data as $single) {
                $grouping_methods[$single['group']][] = $single;
            }
            
            $new_quote_data=array();
            foreach($grouping_methods as $group_id => $grouping_method) {
                if (count($grouping_method) == 1 || empty($group_id) || $xshippingpro_sub_group[$group_id] == 'no_group') {
                    $append_methods = array();
                    foreach($grouping_method as $single) {
                        $append_methods[$single['xkey']] = $single;
                    }
                    $new_quote_data = array_merge($new_quote_data, $append_methods);
                    continue;
                }

                $sub_group_type = $xshippingpro_sub_group[$group_id];
                $sub_group_limit = isset($xshippingpro_sub_group_limit[$group_id])?$xshippingpro_sub_group_limit[$group_id] : 1;
                $sub_group_name = isset($xshippingpro_sub_group_name[$group_id]) ? $this->ocm->html_decode($xshippingpro_sub_group_name[$group_id]) : '';
                $sub_group_desc = isset($xshippingpro_sub_group_desc[$group_id]) ? $this->ocm->html_decode($xshippingpro_sub_group_desc[$group_id]) : '';
                if (isset($grouping_method)) {
                    $new_quote_data = array_merge($new_quote_data, $this->findGroup($grouping_method, $sub_group_type, $sub_group_limit, $sub_group_name, $sub_group_desc));
                }
            }
            $quote_data = $new_quote_data;
       }

       /* calculuate top level grouping if method level groupping active */
       if ($xshippingpro_group != 'no_group' && $method_level_group) {
            $grouping_methods=array();
            foreach($quote_data as $single) {
                $grouping_methods[$single['sort_order']][]=$single;
            }

            $new_quote_data=array();
            foreach($grouping_methods as $group_id => $grouping_method) {
                if (count($grouping_method) == 1 || empty($group_id)) { // Not treating 0 as legible group indentifer
                    $append_methods = array();
                    foreach($grouping_method as $single) {
                       $append_methods[$single['xkey']] = $single;
                    }
                    $new_quote_data = array_merge($new_quote_data, $append_methods);
                    continue;
                }

                if (isset($grouping_method)) {
                   $new_quote_data = array_merge($new_quote_data, $this->findGroup($grouping_method, $xshippingpro_group, $xshippingpro_group_limit));
                }
            }
            $quote_data= $new_quote_data;
        }

        /*Sorting final methods */
        $sort_order = array();
        foreach ($quote_data as $key => $value) {
            if ($xshippingpro_sorting == 2 || $xshippingpro_sorting == 3) {
                $sort_order[$key] = $value['cost'];
            } else if ($xshippingpro_sorting == 4 || $xshippingpro_sorting == 5) {
                $sort_order[$key] = $value['title'];
            } else {
                $sort_order[$key] = $value['sort_order'];
            }
            /* Unset unwanted keys */
            unset($quote_data[$key]['group']);
            unset($quote_data[$key]['xkey']);
            unset($quote_data[$key]['exc_vat']);
            unset($quote_data[$key]['display']);
        }
        $sort_type = ($xshippingpro_sorting == 3 || $xshippingpro_sorting == 5) ? SORT_DESC : SORT_ASC;
        array_multisort($sort_order, $sort_type, $quote_data);

        /* Apply Sub-options */
        $quote_data = $this->addSubOptions($quote_data, $sub_options, $language_id, $currency_code);
        $xshippingpro_heading = isset($xshippingpro_heading[$language_id])?$xshippingpro_heading[$language_id] : '';
        $method_data = array(
            'code'       => 'xshippingpro',
            'title'      => $xshippingpro_heading,
            'quote'      => $quote_data,
            'sort_order' => $this->ocm->getConfig('xshippingpro_sort_order', $this->mtype),
            'error'      => false
        );

        if ($xshippingpro_debug) {
            $this->ocm->writeLog($debugging, 'xshippingpro');
        }

        if ($shipping_error) {
            $method_data['quote'] = array();
            $method_data['error'] = $shipping_error;
            return $method_data;
        }
        return $quote_data ? $method_data : array();
    }

    private function getFormattedError($error, $cart_products, $address, $applicable_products, $rules) {
        $placeholders = array('{postalCode}', '{city}', '{products}', '{zoneName}', '{countryName}');
        $replacers = array($address['postcode'] , $address['city'], '', '', '');
        if (strpos($error, '{products}') !== false) {
            $_product = array();
            foreach ($cart_products as $cart_product) {
                foreach ($applicable_products as $type => $items) {
                    $is_found = false;
                    if (!$items || !isset($rules[$type])) continue;
                    if ($type =='category') {
                        $is_found = (boolean)$this->array_intersect_faster($items, $cart_product['category']);
                    } else if ($type =='manufacturer' || $type =='location' || $type =='product') {
                        $is_found = in_array($cart_product[$type], $items);
                    }
                    //additional check for except rule 
                    if ($rules[$type]['rule_type']==5 || $rules[$type]['rule_type']==7) {
                        $is_found = ($type == 'category' || $type == 'option') ? $this->array_intersect_faster($cart_product[$type], $rules[$type]['value']) : in_array($cart_product[$type], $rules[$type]['value']);
                    }

                    if ($is_found) {
                        $url =  $this->url->link('product/product', 'product_id=' . $cart_product['product_id']); $cart_product['product_id'];
                        $_product[$cart_product['product_id']]= '<a href="'.$url.'">'.$cart_product['name'].'</a>';
                        break;
                    }
                }
            }
            $replacers[2] = implode(',&nbsp;&nbsp;', $_product);
        }
        if (strpos($error, '{zoneName}') !== false) {
            if (!isset($address['zoneName'])) {
                $address['zoneName'] = $this->db->query("SELECT name FROM " . DB_PREFIX . "zone WHERE zone_id = '" . (int)$address['zone_id'] . "'")->row['name'];
            }
            $replacers[3] = $address['zoneName'];
        }
        if (strpos($error, '{countryName}') !== false) {
            $replacers[4] = $this->db->query("SELECT name FROM " . DB_PREFIX . "country WHERE country_id = '" . (int)$address['country_id'] . "'")->row['name'];
        }
        return str_replace($placeholders, $replacers, $error);
   }

   private function addSubOptions($quote_data, $sub_options, $language_id, $currency_code) {
        /* Don't add Sub-Options if it is estimator or quote page */
        if (isset($this->request->post['_xestimator']) || $this->ocm->isQuotePage()) {
            return $quote_data;
        }

        if ($sub_options) {
            foreach ($sub_options as $tab_id => $sub_option) {
                if(isset($quote_data['xshippingpro'.$tab_id])) {
                    foreach ($sub_option as $single_option) {
                        if (!isset($single_option['name'][$language_id]) || !$single_option['name'][$language_id]) {
                            $single_option['name'][$language_id] = 'Untitled Option';
                        }
                        $cost = $quote_data['xshippingpro'.$tab_id]['cost'];
                        $title = $quote_data['xshippingpro'.$tab_id]['title'];
                        $tax_class_id = $quote_data['xshippingpro'.$tab_id]['tax_class_id'];
                        $fo = $quote_data['xshippingpro'.$tab_id]['fo'];
                        $text = '';

                        if ($fo && !$cost) {
                            $single_option['cost'] = 0;
                            $text = '!!--';
                        }

                        $option_id = $single_option['option_id'];
                        $operator = $single_option['operator'];
                        
                        if ($single_option['cost']) {
                            if ($operator == '+') {
                                $cost += $single_option['cost'];
                            } else if ($operator == '-') {
                                $cost -= $single_option['cost'];
                                $cost = $cost > 0 ? $cost : 0;
                            } else {
                                $cost = $single_option['cost'];
                            }
                            $text = $this->currency->format($this->tax->calculate($cost, $tax_class_id, $this->config->get('config_tax')), $currency_code);
                        }

                        $option_quote = array(
                            'code'         => 'xshippingpro'.'.xshippingpro' . $tab_id . '_' . $option_id,
                            'title'        => $title .' - '. $single_option['name'][$language_id],
                            'cost'         => $cost,
                            'tax_class_id' => $tax_class_id,
                            'text'         => $text
                        );
                        $quote_data['xshippingpro' . $tab_id . '_' . $option_id] = $option_quote;
                    }
                }
            }
        }
        return $quote_data;
   }

   public function getShippingDesc() {
        $language_id = $this->config->get('config_language_id');
        $desc = array();
        $logo = array();
        $xshippings = $this->getShippings();
        foreach($xshippings['xmethods'] as $xshippingpro) {
            $tab_id = $xshippingpro['tab_id'];
            $_desc = (isset($xshippingpro['desc'][$language_id]) && $xshippingpro['desc'][$language_id]) ? $this->ocm->html_decode($xshippingpro['desc'][$language_id]) : '';
            if ($_desc) {
               $desc[$tab_id] = $_desc;
            }
            if ($xshippingpro['logo']) {
                $logo[$tab_id] = $xshippingpro['logo'];
            }
        }
        return array('desc' => $desc, 'logo' => $logo, 'city' => $xshippings['xmeta']['city_rule'], 'payment' => $xshippings['xmeta']['payment_rule']);
    }

   private function getSubOptions() {
       $language_id = $this->config->get('config_language_id');
       $currency_code = isset($this->session->data['currency']) ? $this->session->data['currency'] : $this->config->get('config_currency');

       $sub_options = array();
       $xshippings = $this->getShippings();
       foreach($xshippings['xmethods'] as $xshippingpro) {
            $tab_id = $xshippingpro['tab_id'];
            $tax_class_id = $xshippingpro['tax_class_id'];
            $method_options = array();
            foreach ($xshippingpro['sub_options'] as $single_option) {
                $option_id = $single_option['option_id'];
                $operator = $single_option['operator'];
                $text = '';
                if ($single_option['cost']) {
                    $option_text = $xshippingpro['exc_vat'] ? $this->currency->format($single_option['cost'], $currency_code) : $this->currency->format($this->tax->calculate($single_option['cost'], $tax_class_id, $this->config->get('config_tax')), $currency_code);
                    $text = ' (' . $operator . $option_text . ')';
                }

                if (!isset($single_option['name'][$language_id]) || !$single_option['name'][$language_id]) {
                    $single_option['name'][$language_id] = 'Untitled Option';
                }
                $single_option['name'][$language_id] .= $text;

                $method_options[] = array(
                    'code'         => 'xshippingpro'.'.xshippingpro' . $tab_id . '_' . $option_id,
                    'title'        => $single_option['name'][$language_id],
                    'cost'         => $single_option['cost']
                );
            }

            if ($method_options) {
                $sub_options[$tab_id] = $method_options;
            }
        }
        return $sub_options;
   }

   private function iterateEquation($equation, $type, $rates, $method_specific_data, $_cart_data, $quote_data, $percent_of, $cost = 0, $modifier_amount = 0) {
        $all = array('{productWidth}', '{productHeight}', '{productLength}', '{productWeight}', '{productStock}', '{productPrice}');
        $any = array('{anyProductWidth}', '{anyProductHeight}', '{anyProductLength}', '{anyProductWeight}', '{anyProductStock}', '{anyProductPrice}');
        $_equation_status = false;
        $_equation_cost = $cost;
        $_placeholders = $type == 'range' ? $all : $any;
        $_equation = $equation;
        foreach ($method_specific_data['products'] as $product) {
            $_replacers = array($product['width_self'], $product['height_self'], $product['length_self'], $product['weight_self'], $product['stock'], $product['price_self']);
            $equation = str_replace($_placeholders, $_replacers, $_equation);
            $target_value = $this->getEquationValue($equation, $_cart_data, $method_specific_data, $quote_data, $percent_of, $_equation_cost, $modifier_amount);

            if ($type == 'range') {
                $target_value = $rates['cart_adjust'] ? $this->adjustValue($rates['cart_adjust'], $percent_of, $target_value) : $target_value;
                $_equation_result = $this->getPrice($rates, $target_value, $percent_of);
                if ($_equation_result['status']) {
                    $_equation_cost += ($rates['final'] == 'single' ? $_equation_result['cost'] : $_equation_result['cumulative']) * $product['quantity'];
                    $_equation_status = true;
                }
            } else {
                if ($target_value >= 0) {
                    $_equation_status = true;
                    $_equation_cost += $target_value;
                    $method_specific_data['shipping'] = $_equation_cost; // update shipping for placeholder
                    if ($type == 'single') {
                        break;
                    }
                }
            }
        }
        return $_equation_status ? $_equation_cost : false;
    }
    private function findGroup($group_method, $group_type, $group_limit, $group_name ='', $group_desc='') {
        $language_id = $this->config->get('config_language_id');
        $currency_code = isset($this->session->data['currency']) ? $this->session->data['currency'] : $this->config->get('config_currency');
        $return = array();
        $replacer_name = array();
        $replacer_price = array();
        if ($group_type == 'lowest') {
            $lowest=array();
            $lowest_sort=array();
            foreach($group_method as $group_id=>$method) {
                $lowest_sort[$group_id] = $method['cost'];
                $lowest[$group_id] = $method;
                array_push($replacer_name, $method['title']);
                array_push($replacer_price, $this->currency->format((float)$method['cost'], $currency_code, false, true));
            }
            array_multisort($lowest_sort, SORT_ASC, $lowest);
            for($i=0;$i<$group_limit;$i++) {
                if (isset($lowest[$i]) && is_array($lowest[$i]) && $lowest[$i]) {   
                    $return[$lowest[$i]['xkey']] = $lowest[$i]; 
                }
            }
        }
        if ($group_type == 'highest') {
            $highest=array();
            $highest_sort=array();
            foreach($group_method as $group_id => $method) {
                $highest_sort[$group_id] = $method['cost'];
                $highest[$group_id] = $method;
                array_push($replacer_name, $method['title']);
                array_push($replacer_price, $this->currency->format((float)$method['cost'], $currency_code, false, true));
            }
            array_multisort($highest_sort, SORT_DESC, $highest);
            for($i=0;$i<$group_limit;$i++) {
                if (isset($highest[$i]) && is_array($highest[$i]) && $highest[$i]) {    
                    $return[$highest[$i]['xkey']] = $highest[$i]; 
                }
            } 
        } 
        if ($group_type == 'average') {
            $sum=0;
            foreach($group_method as $group_id => $method) {
                $sum+=$method['cost'];
                array_push($replacer_name, $method['title']);
                array_push($replacer_price, $this->currency->format((float)$method['cost'], $currency_code, false, true));
            }
            if (count($group_method) > 1) {
                $group_method[0]['cost']=$sum/count($group_method); 
                $group_method[0]['text']=$this->currency->format($this->tax->calculate($group_method[0]['cost'], $group_method[0]['tax_class_id'], $this->config->get('config_tax')),$currency_code);
            }
            $return[$group_method[0]['xkey']]= $group_method[0];
        } 
        if ($group_type == 'sum') {
            $sum=0;
            foreach($group_method as $group_id => $method) {
                $sum += $method['cost'];
                array_push($replacer_name, $method['title']);
                array_push($replacer_price, $this->currency->format((float)$method['cost'], $currency_code, false, true));
            }
            $group_method[0]['cost'] = $sum;
            $group_method[0]['text'] = $this->currency->format($this->tax->calculate($group_method[0]['cost'], $group_method[0]['tax_class_id'], $this->config->get('config_tax')),$currency_code);
            $return[$group_method[0]['xkey']]= $group_method[0];  
        }

        if ($group_name || $group_desc) {
            $replacer_name_price = array();
            foreach ($replacer_name as $key => $value) {
                $replacer_name_price[] = $value .'-' . $replacer_price[$key];
            }
            $keywords = array('@#','@','#');
            $replacer = array();
            $replacer[] = implode('+', $replacer_name_price);
            $replacer[] = implode('+', $replacer_name);
            $replacer[] = implode('+', $replacer_price);
            $group_name = str_replace($keywords, $replacer, $group_name);
            $group_desc = str_replace($keywords, $replacer, $group_desc);
        }

        if (count($return) == 1 && ($group_name || $group_desc)) {
            foreach($return as $key => $method) {
                if ($group_name) {
                    $return[$key]['title'] = $group_name;
                }
                if ($group_desc) {
                    $return[$key]['desc'] = '<div style="color: #999999;font-size: 11px;display:block" class="x-shipping-desc">'.$group_desc.'</div>';
                }
            }
        }
        return $return;
    }

    private function getPrice($rates, $target_value, $percent_of) {
        $ranges = $rates['ranges'];
        $status = false;
        $cost = 0;
        $block = 0;
        $end = 0;
        $cumulative = 0;
        $target_value = round($target_value, 3);
        foreach($ranges as $range) {
            $start = $range['start'];
            $end = $range['end'];
            if ($start && !$end) {
                $end = PHP_INT_MAX;
            }
            $cost = $range['percent'] ? ($range['value'] * $percent_of) : $range['value'];
            if ($start <= $target_value && $target_value <= $end) {
                $status = true; 
                $end = $target_value;
            }
            $block = $range['block'];
            $partial = $range['partial'];
            if ($block > 0) {
                /* round to complete block for iteration purpose. 
                  For negetive value, round to previous round and for positive value round to next round.
                */
                if (!$partial) {
                    if(is_float($end) && fmod($end, $block) != 0) {
                        $end = $cost < 0 ? ($end - fmod($end, $block)) : ($end - fmod($end, $block)) + $block;
                    }
                    else if($block >= 1 && ($end % $block) != 0) {
                       $end =  $cost < 0 ? ($end - ($end % $block)) : ($end - ($end % $block)) + $block; 
                    }
                }
                $no_of_blocks = 0;
                if ($start == 0 && !$partial) {
                    $start = 1;
                }
                while($start <= $end) {
                    if ($partial) {
                        $no_of_blocks =  ($end-$start) >= $block ? ($no_of_blocks + 1) : ($no_of_blocks + ($end - $start) / $block);
                    } else {
                        $no_of_blocks++;
                    }
                    $start += $block;
                }
                $cost = ($no_of_blocks * $cost);
            }
            $cumulative += $cost;
            if ($status) break;
        }

         /* if not found and additional price was set */
        if (!$status && $rates['additional'] && $rates['additional']['max'] >= $target_value) {
            $additional = $rates['additional']['percent'] ? ($rates['additional']['value'] * $percent_of) : $rates['additional']['value'];
            $additional_per = $rates['additional']['block'];
            while($end < $target_value) {
                $cost += $additional;
                $cumulative += $additional;
                $end += $additional_per;
            }
            $status = true;
        }

        return array(
            'cost' => $status ? $cost : 0,
            'cumulative' => $cumulative,
            'status' => $status
        );
    }

    private function calculate_string($str) {

          $__eval = function ($str) use(&$__eval){
              $error = false;
              $div_mul = false;
              $add_sub = false;
              $result = 0;

              $str = preg_replace('/[^\d.+\-*\/()]/i','',$str);
              $str = rtrim(trim($str, '/*+'),'-');

              /* lets first tackle parentheses */
              if ((strpos($str, '(') !== false &&  strpos($str, ')') !== false)) {
                  $regex = '/\(([\d.+\-*\/]+)\)/';
                  preg_match($regex, $str, $matches);
                  if (isset($matches[1])) {
                     return $__eval(preg_replace($regex, $__eval($matches[1]), $str, 1));
                  }
              }

              /* Remove unwanted parentheses */
              $str = str_replace(array('(',')'), '', $str);
              /* now division and multiplication */
              if ((strpos($str, '/') !== false ||  strpos($str, '*') !== false)) {
                 $div_mul = true;
                 $operators = array('*','/');
                  while(!$error && $operators) {
                    $operator = array_pop($operators);
                    while($operator && strpos($str, $operator) !== false) {
                       if ($error) {
                          break;
                       }
                       $regex = '/([\d.]+)\\'.$operator.'(\-?[\d.]+)/';
                       preg_match($regex, $str, $matches);
                       if (isset($matches[1]) && isset($matches[2])) {
                              if ($operator=='+') $result = (float)$matches[1] + (float)$matches[2];
                              if ($operator=='-') $result = (float)$matches[1] - (float)$matches[2]; 
                              if ($operator=='*') $result = (float)$matches[1] * (float)$matches[2]; 
                              if ($operator=='/') {
                                 if ((float)$matches[2]) {
                                    $result = (float)$matches[1] / (float)$matches[2];
                                 } else {
                                    $error = true;
                                 }
                              }
                              $str = preg_replace($regex, $result, $str, 1);
                              $str = str_replace(array('++','--','-+','+-'), array('+','+','-','-'), $str);
                       } else {
                          $error = true;
                       }
                    }
                  }
              }
            
              if (!$error && (strpos($str, '+') !== false ||  strpos($str, '-') !== false)) {
                 $add_sub = true;
                 preg_match_all('/([\d\.]+|[\+\-])/', $str, $matches);
                 if (isset($matches[0])) {
                     $result = 0;
                     $operator = '+';
                     $tokens = $matches[0];
                     $count = count($tokens);
                     for ($i=0; $i < $count; $i++) { 
                         if ($tokens[$i] == '+' || $tokens[$i] == '-') {
                            $operator = $tokens[$i];
                         } else {
                            $result = ($operator == '+') ? ($result + (float)$tokens[$i]) : ($result - (float)$tokens[$i]);
                         }
                     }
                 }
              }
              if (!$error && !$div_mul && !$add_sub) {
                 $result = (float)$str;
              }
              return $error ? 0 : $result;
          };

          if (strpos($str, '?') !== false) {
              preg_match('/(.*)\?(.*):(.*)/', $str, $matches);
              if (count($matches) == 4) {
                 $__is_condition_true = function ($str) use ($__eval) {
                       preg_match('/(.+?)([!<>=]+)(.+)/', $str, $matches);
                       if (count($matches) == 4) {
                           $left = $__eval($matches[1]);
                           $right = $__eval($matches[3]);
                           $cond = trim($matches[2]);
                           if ($cond =='===' || $cond =='==') {
                              $is_success = ($left == $right);
                           } else if ($cond =='!==' || $cond =='!=') {
                              $is_success = ($left != $right);
                           } else if ($cond =='>') {
                              $is_success = ($left > $right);
                           } else if ($cond =='<') {
                              $is_success = ($left < $right);
                           } else if ($cond =='<=') {
                              $is_success = ($left <= $right);
                           } else if ($cond =='>=') {
                              $is_success = ($left >= $right);
                           } else {
                              $is_success = false;
                           }
                       } else {
                          $is_success = false;
                       }
                       return $is_success;
                 };
                 return $__is_condition_true($matches[1]) ? $__eval($matches[2]) : $__eval($matches[3]);
              } else {
                return 0;
              }
          } else {
              return $__eval($str);
          }
    }

    private function _validateDimension($dimension, $cart_products, $rule_type) {
        $is_valid = true;
        foreach ($cart_products as $product) {
            if ($product['width_self'] > $dimension['width'] || $product['height_self'] > $dimension['height'] || $product['length_self'] > $dimension['length']) {
                $is_valid = false;
                break;
            }
        }
        return $is_valid;
    }
    private function _validateProduct($method_products, $cart_products, $rule_type) {
        $status = true;
        $resultant_data = array_intersect($method_products, $cart_products);

        if ($rule_type == 2) {
             if (count($resultant_data) != count($method_products)) {
                $status = false; 
             }
        }
        if ($rule_type==3) {
            if (!$resultant_data) {
                $status = false; 
            }
        }
        if ($rule_type == 4) {
            if (count($resultant_data) != count($method_products) || count($resultant_data) != count($cart_products)) {
                $status = false; 
            }
        }
        if ($rule_type == 5) {
            if ($resultant_data) {
                $status = false; 
            }
        }
        if ($rule_type == 6) {
            if (!$resultant_data || count($resultant_data) != count($cart_products)) {
                $status = false; 
            }
        }
        if ($rule_type == 7) {
            if ($resultant_data && count($resultant_data) == count($cart_products)) {
                $status = false; 
            }
        }
        return $status;
    }

    private function _validatePostal($postcodes, $deliver_postal, $rule_type) {
        $status = false;
        foreach($postcodes as $postcode) {
            if (!$postcode) continue;
            /* regex ifrst otherwise dash in rex can interfere range*/
            if (substr($postcode,0,1) == '/') {
                if (preg_match($postcode, $deliver_postal)) {
                    $status = true; 
                    break;
                }
            }
            /* Postal Range - Only Numeric */
            elseif (strpos($postcode,'-') !== false && substr_count($postcode,'-') == 1) {
                list($start_postal,$end_postal) = explode('-',$postcode); 
                $start_postal = (int)$start_postal;
                $end_postal = (int)$end_postal;
                if ( $deliver_postal >= $start_postal &&  $deliver_postal <= $end_postal) {
                    $status = true;
                }
            }
           /* Range postal code with prefix*/
            elseif (strpos($postcode,'-') !== false && substr_count($postcode,'-') == 2) {
                list($prefix,$start_postal,$end_postal) = explode('-',$postcode);
                $start_postal = (int)$start_postal;
                $end_postal = (int)$end_postal;
                if ($start_postal <= $end_postal) {
                    for($i = $start_postal;$i <= $end_postal; $i++) {
                        if (preg_match('/^'.str_replace(array('\*','\?'),array('(.*?)','[a-zA-Z0-9]'),preg_quote($prefix.$i)).'$/i',$deliver_postal)) {
                            $status = true; 
                            break; 
                        }
                    }
                }
            }
            /* Range postal code with prefix and sufiix*/
            elseif (strpos($postcode,'-') !== false && substr_count($postcode,'-') == 3) {
                list($prefix,$start_postal,$end_postal,$sufiix) = explode('-',$postcode); 
                $start_postal = (int)$start_postal;
                $end_postal = (int)$end_postal;
                if ($start_postal <= $end_postal) {
                    for($i = $start_postal; $i <= $end_postal; $i++) {
                        if (preg_match('/^'.str_replace(array('\*','\?'),array('(.*?)','[a-zA-Z0-9]'),preg_quote($prefix.$i.$sufiix)).'$/i',$deliver_postal)) {
                            $status = true;  
                            break;
                        }
                    }
                }
            }
            /* wildcards use code*/
            elseif (strpos($postcode,'*') !== false || strpos($postcode,'?') !== false) {
                if (preg_match('/^'.str_replace(array('\*','\?'),array('(.*?)','[a-zA-Z0-9]'),preg_quote($postcode)).'$/i',$deliver_postal)) {
                    $status = true;
                    break;
                }
            }
            /* Simple equality check */
            else {
                if ($deliver_postal == strtolower($postcode)) {
                    $status = true;
                    break;
                } 
            }
        }
        $rule_type = ($rule_type == 'inclusive') ? true : false;
        return ($status === $rule_type);
    }

    private function getProductProfile($cart_products, $xmeta) {
            $cart_categories = array();
            $cart_product_ids = array();
            $cart_manufacturers = array();
            $cart_options = array();
            $cart_locations = array();
            $cart_volume = 0;
            $cart_quantity = 0;
            $cart_weight = 0;
            $cart_sub = 0;
            $cart_total = 0;
            $cart_ean = 0;
            $cart_jan = 0;
            $multi_category = false;
            $tax_data = array();

            foreach($cart_products as $inc=>$product) {
                $cart_product_ids[] = $product['product_id']; 
                $cart_products[$inc]['product'] = $product['product_id']; /* Use same key for all places */
                $tax_class_id = isset($product['tax_class_id']) ? $product['tax_class_id'] : 0;
                $total_with_tax = $this->tax->calculate($product['price'], $tax_class_id, $this->config->get('config_tax')) * $product['quantity'];

                $weight_class_id = $product['weight_class_id'] ? $product['weight_class_id'] : $this->config->get('config_weight_class_id');
                $weight = $this->weight->convert($product['weight'], $weight_class_id, $this->config->get('config_weight_class_id'));

                $cart_products[$inc]['category'] = array();

                if ($xmeta['category_query']) {
                    $product_categories = $this->db->query("SELECT category_id FROM " . DB_PREFIX . "product_to_category WHERE product_id = '" . (int)$product['product_id'] . "'")->rows;
                    if ($product_categories) {
                        if (count($product_categories)>1) $multi_category = true;
                        foreach($product_categories as $category) {
                            $cart_categories[]=$category['category_id'];  
                            $cart_products[$inc]['category'][]=$category['category_id']; //store for future use 
                        } 
                    }
                }

                $length_class_id = $product['length_class_id'] ? $product['length_class_id'] : $this->config->get('config_length_class_id');
                $length = $this->length->convert($product['length'], $length_class_id, $this->config->get('config_length_class_id'));
                $width = $this->length->convert($product['width'], $length_class_id, $this->config->get('config_length_class_id'));
                $height = $this->length->convert($product['height'], $length_class_id, $this->config->get('config_length_class_id'));

                $volume = ($width * $height * $length);
                $cart_volume += ($volume * $product['quantity']);
                $cart_quantity += $product['quantity'];
                $cart_sub += $product['total'];
                $cart_total += $total_with_tax;
                $cart_weight += $weight;

                $cart_products[$inc]['length'] = $product['length'] * $product['quantity'];
                $cart_products[$inc]['width'] = $product['width'] * $product['quantity'];
                $cart_products[$inc]['height'] = $product['height'] * $product['quantity'];
                $cart_products[$inc]['total_with_tax'] = $total_with_tax;
                $cart_products[$inc]['volume'] = $volume * $product['quantity'];
                $cart_products[$inc]['weight'] = $weight;
                $cart_products[$inc]['length_self'] = $length;
                $cart_products[$inc]['width_self'] = $width;
                $cart_products[$inc]['height_self'] = $height;
                $cart_products[$inc]['volume_self'] = $volume; 
                $cart_products[$inc]['weight_self'] = ($weight / $product['quantity']);
                $cart_products[$inc]['price_self'] = $product['price'];

                if ($xmeta['product_query']) {
                    $product_info = $this->db->query("SELECT manufacturer_id, location, jan, ean FROM " . DB_PREFIX . "product WHERE product_id = '" . (int)$product['product_id'] . "'")->row;
                    if ($product_info){
                        $cart_ean += (float)$product_info['ean'] * $product['quantity'];
                        $cart_jan += (float)$product_info['jan'] * $product['quantity'];

                        $cart_manufacturers[] = $product_info['manufacturer_id'];
                        $cart_products[$inc]['manufacturer'] = $product_info['manufacturer_id']; //store for future use
                        $location = trim(strtolower($product_info['location']));
                        if ($location) {
                            $cart_products[$inc]['location'] = $location; //store for future use
                            $cart_locations[] = $location;
                        }
                    }
                }
                
                $cart_products[$inc]['option'] = array();
                if (isset($product['option']) && $product['option'] && is_array($product['option'])) {
                    foreach($product['option'] as $option) {
                        if ($option['type'] == 'select' || $option['type'] == 'radio' || $option['type'] == 'checkbox') {
                            $cart_options[] = $option['option_value_id'];  
                            $cart_products[$inc]['option'][] = $option['option_value_id']; //store for future use 
                        }
                    }
                }

                /* Tax Data */
                if ($product['tax_class_id']) {
                    $tax_rates = $this->tax->getRates($product['price'], $product['tax_class_id']);
                    foreach ($tax_rates as $tax_rate) {
                        if (!isset($tax_data[$tax_rate['tax_rate_id']])) $tax_data[$tax_rate['tax_rate_id']] = 0;
                        $tax_data[$tax_rate['tax_rate_id']] += ($tax_rate['amount'] * $product['quantity']);
                    }
                }
            } 

            $cart_categories = array_unique($cart_categories);
            $cart_product_ids = array_unique($cart_product_ids);
            $cart_manufacturers = array_unique($cart_manufacturers);
            $cart_options = array_unique($cart_options);
            $cart_locations = array_unique($cart_locations);

            return array(
                'products' => $cart_products,
                'category' => $cart_categories,
                'product' => $cart_product_ids,
                'manufacturer' => $cart_manufacturers,
                'option' => $cart_options,
                'location' => $cart_locations,
                'volume' => $cart_volume,
                'multi_category' => $multi_category,
                'no_category' => count($cart_categories),
                'no_manufacturer' => count($cart_manufacturers),
                'no_location' => count($cart_locations),
                'quantity' => $cart_quantity,
                'weight' => $cart_weight,
                'total' => $cart_total,
                'sub' => $cart_sub,
                'grand' => $cart_total,
                'grand_shipping' => $cart_total,
                'jan' => $cart_jan,
                'ean' => $cart_ean,
                'coupon' => 0,
                'reward' => 0,
                'distance' => 0,
                'tax_data' => $tax_data,
                'xfeepro' => array()
            );
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
                'category_query'=> false,
                'product_query' => false,
                'payment_rule'  => false,
                'distance'      => false,
                'city_rule'     => false
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

                    $have_product_specified = false;
                    if ($method_data['category'] > 1
                        || $method_data['product'] > 1
                        || $method_data['manufacturer_rule'] > 1
                        || $method_data['option'] > 1
                        || $method_data['location_rule'] > 1) {
                            $have_product_specified = true;
                    }

                    $xmethods[] = array(
                       'tab_id' => (int)$xshippingpro_row['tab_id'],
                       'name' => $method_data['name'],
                       'desc' => $method_data['desc'],
                       'mask' => $method_data['mask'],
                       'error' => $method_data['error'],
                       'display' => $method_data['display'],
                       'rules' => $rules,
                       'rates' => $rates,
                       'group' => (int)$method_data['group'],
                       'inc_weight' => !!$method_data['inc_weight'],
                       'exc_vat' => !!$method_data['exc_vat'],
                       'equation_neg' => !!$method_data['equation_neg'],
                       'tax_class_id' => (int)$method_data['tax_class_id'],
                       'sort_order' => (int)$method_data['sort_order'],
                       'logo' => $method_data['logo'],
                       'ingore_product_rule' => !!$method_data['ingore_product_rule'],
                       'product_or' => !!$method_data['product_or'],
                       'method_specific' => !!$method_data['method_specific'],
                       'free_option' => !!$method_data['free_option'],
                       'hide' => $method_data['hide'],
                       'hide_inactive' => $method_data['hide_inactive'],
                       'need_hide_method' => !!count($method_data['hide']),
                       'need_inactive_hide_method' => !!count($method_data['hide_inactive']),
                       'have_product_specified' => $have_product_specified,
                       'disable' => !!$method_data['disable'],
                       'sub_options' => $method_data['sub_options']
                    );

                    if ($method_data['geo_zone_all'] != 1) {
                        $xmeta['geo'] = true;
                    }
                    if ($method_data['payment_all'] != 1) {
                        $xmeta['payment_rule'] = true;
                    }
                    if ($method_data['city_all'] != 1) {
                        $xmeta['city_rule'] = true;
                    }
                    if ($method_data['rate_type'] == 'grand_shipping'
                        || $method_data['rate_type'] == 'grand'
                        || $method_data['rate_type'] == 'equation'
                        || strpos($method_data['equation'], 'grandTotal') !== false
                        || strpos($method_data['equation'], 'grandBeforeShipping') !== false) {
                        $xmeta['grand'] = true;
                    }
                    if ($method_data['rate_type'] == 'total_coupon' || $method_data['equation']) {
                        $xmeta['coupon'] = true;
                    }
                    if ($method_data['category'] > 1
                        || $method_data['rate_type'] == 'no_category'
                        || strpos($method_data['equation'], 'noOfCategory') !== false) {
                            $xmeta['category_query'] = true;
                    }
                    if ($method_data['manufacturer_rule'] > 1
                        || $method_data['location_rule'] > 1
                        || $method_data['rate_type'] == 'no_manufacturer'
                        || $method_data['rate_type'] == 'no_location'
                        || strpos($method_data['equation'], 'noOfManufacturer') !== false
                        || strpos($method_data['equation'], 'jan') !== false
                        || strpos($method_data['equation'], 'ean') !== false
                        || strpos($method_data['equation'], 'noOfLocation') !== false) {
                            $xmeta['product_query'] = true;
                    }
                    if ($method_data['rate_type'] == 'distance'
                        || strpos($method_data['equation'], 'distance') !== false) {
                            $xmeta['distance'] = true;
                    }
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
            'city' => 'city_all',
            'country' => 'country_all',
            'zone' => 'zone_all',
            'customer_group' => 'customer_group_all',
            'currency' => 'currency_all',
            'payment' => 'payment_all',
            'postal' => 'postal_all',
            'coupon' => 'coupon_all',
            'days' => 'days_all',
            'product_category' => 'category',
            'product_product' => 'product',
            'product_option' => 'option',
            'manufacturer' => 'manufacturer_rule',
            'location' => 'location_rule',
            'customers' => 'customer'
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

        /* reset delimitter to comma */
        $fields = array(
            'city',
            'coupon',
            'postal'
        );
        foreach ($fields as $field) {
            if (isset($data[$field]) && $data[$field]) {
                $data[$field] = str_replace(PHP_EOL, ',', $data[$field]);
            }
        }

        /* reset cost params  */ 
        if (!isset($data['ranges'])) $data['ranges'] = array();
        if (!isset($data['additional_per']) || !$data['additional_per']) $data['additional_per'] = 1;
        if (!isset($data['additional_limit']) || !$data['additional_limit']) $data['additional_limit'] = PHP_INT_MAX;
        if (!isset($data['dimensional_factor']) || !$data['dimensional_factor']) $data['dimensional_factor'] = 5000;
        if (!isset($data['dimensional_overfule']) || !$data['dimensional_overfule']) $data['dimensional_overfule'] = '';

        /* checkboxes */
        if (!isset($data['inc_weight'])) $data['inc_weight'] = '';
        if (!isset($data['ingore_product_rule'])) $data['ingore_product_rule'] = '';
        if (!isset($data['product_or'])) $data['product_or'] = '';
        if (!isset($data['method_specific'])) $data['method_specific'] = '';
        if (!isset($data['free_option'])) $data['free_option'] = '';
        if (!isset($data['dimensional_overfule'])) $data['dimensional_overfule'] = '';
        if (!isset($data['exc_vat'])) $data['exc_vat'] = '';
        if (!isset($data['equation_neg'])) $data['equation_neg'] = '';
        if (!isset($data['disable'])) $data['disable'] = '';

        /* Reset other */
        if (!isset($data['hook'])) $data['hook'] = array();
        if (!isset($data['days'])) $data['days'] = array();
        if (!isset($data['name']) || !is_array($data['name'])) $data['name']=array();
        if (!isset($data['desc']) || !is_array($data['desc'])) $data['desc']=array();
        if (!isset($data['mask']) || !is_array($data['mask'])) $data['mask']=array();
        if (!isset($data['hide']) || !is_array($data['hide'])) $data['hide']=array();
        if (!isset($data['hide_inactive']) || !is_array($data['hide_inactive'])) $data['hide_inactive']=array();
        if (!isset($data['display']) || !$data['display']) $data['display'] = 'Untitled Method';
        
        /* Adjust Sub-Options */
        if (!isset($data['sub_options']) || !is_array($data['sub_options'])) $data['sub_options']=array();
        foreach ($data['sub_options'] as $index => $single_option) {
            $operator = substr(trim($single_option['cost']),0,1);
            $data['sub_options'][$index]['operator'] = ($operator == '+' || $operator == '-') ? $operator : '';
            $data['sub_options'][$index]['cost'] = (float)(trim($single_option['cost'], '+-'));
            $data['sub_options'][$index]['option_id'] = $index;
        }
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
        if ($data['customer'] != 1) {
            $false_value = ($data['customer_rule'] == 'inclusive') ? false : true;
            $rules['customers'] = array(
                'type' => 'in_array',
                'product_rule' => false,
                'address_rule' => false,
                'value' => $data['customers'],
                'compare_with' => 'customer_id',
                'false_value' => $false_value
            );
        }
        if ($data['city_all'] != 1) {
            $false_value = ($data['city_rule'] == 'inclusive') ? false : true;
            $cities = explode(',',trim($data['city']));
            $cities = array_map('strtolower', $cities);
            $cities = array_map('trim', $cities);

            $rules['city'] = array(
                'type' => 'in_array',
                'product_rule' => false,
                'address_rule' => true,
                'value' => $cities,
                'compare_with' => 'city',
                'false_value' => $false_value
            );
        }
        if ($data['country_all'] != 1) {
            $rules['country'] = array(
                'type' => 'in_array',
                'product_rule' => false,
                'address_rule' => true,
                'value' => $data['country'],
                'compare_with' => 'country_id',
                'false_value' => false
            );
        }
        if ($data['zone_all'] != 1) {
            $rules['zone'] = array(
                'type' => 'in_array',
                'product_rule' => false,
                'address_rule' => true,
                'value' => $data['zone'],
                'compare_with' => 'zone_id',
                'false_value' => false
            );
        }
        if ($data['customer_group_all'] != 1) {
            $rules['customer_group'] = array(
                'type' => 'in_array',
                'product_rule' => false,
                'address_rule' => false,
                'value' => $data['customer_group'],
                'compare_with' => 'customer_group_id',
                'false_value' => false
            );
        }
        if ($data['currency_all'] != 1) {
            $rules['currency'] = array(
                'type' => 'in_array',
                'product_rule' => false,
                'address_rule' => false,
                'value' => $data['currency'],
                'compare_with' => 'currency_id',
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
        if ($data['postal_all'] != 1) {
            $postcodes = explode(',',trim($data['postal']));
            $postcodes = array_map('trim', $postcodes);
            $rules['postal'] = array(
                'type' => 'function',
                'func' => '_validatePostal',
                'product_rule' => false,
                'address_rule' => true,
                'value' => $postcodes,
                'compare_with' => 'postcode',
                'rule_type' => $data['postal_rule'],
                'false_value' => false
            );
        }
        if ($data['coupon_all'] != 1) {
            $false_value = ($data['coupon_rule'] == 'inclusive') ? false : true;
            $coupons = explode(',',trim($data['coupon']));
            $coupons = array_map('trim', $coupons);
            $coupons = array_map('strtolower', $coupons);
            $rules['coupon'] = array(
                'type' => 'in_array',
                'product_rule' => false,
                'address_rule' => false,
                'value' => $coupons,
                'compare_with' => 'coupon_code',
                'false_value' => $false_value
            );
        }
        if ((int)$data['product'] > 1) {
            $rules['product'] = array(
                'type' => 'function',
                'func' => '_validateProduct',
                'product_rule' => true,
                'address_rule' => false,
                'value' => $data['product_product'],
                'compare_with' => 'product',
                'rule_type' => $data['product'],
                'false_value' => false
            );
        }
        if ((int)$data['category'] > 1) {
            $rules['category'] = array(
                'type' => 'function',
                'func' => '_validateProduct',
                'product_rule' => true,
                'address_rule' => false,
                'value' => $data['product_category'],
                'compare_with' => 'category',
                'rule_type' => $data['category'],
                'false_value' => false
            );
        }
        if ((int)$data['manufacturer_rule'] > 1) {
            $rules['manufacturer'] = array(
                'type' => 'function',
                'func' => '_validateProduct',
                'product_rule' => true,
                'address_rule' => false,
                'value' => $data['manufacturer'],
                'compare_with' => 'manufacturer',
                'rule_type' => $data['manufacturer_rule'],
                'false_value' => false
            );
        }
        if ((int)$data['option'] > 1) {
            $rules['option'] = array(
                'type' => 'function',
                'func' => '_validateProduct',
                'product_rule' => true,
                'address_rule' => false,
                'value' => $data['product_option'],
                'compare_with' => 'option',
                'rule_type' => $data['option'],
                'false_value' => false
            );
        }
        if ((int)$data['location_rule'] > 1) {
            $location = array_map('strtolower', $data['location']);
            $location = array_map('trim', $location);
            $rules['location'] = array(
                'type' => 'function',
                'func' => '_validateProduct',
                'product_rule' => true,
                'address_rule' => false,
                'value' => $location,
                'compare_with' => 'location',
                'rule_type' => $data['location_rule'],
                'false_value' => false
            );
        }

        if ($data['days_all'] != 1 && is_array($data['days']) && $data['days'] && count($data['days']) !== 7) {
            $rules['days'] = array(
                'type' => 'in_array',
                'product_rule' => false,
                'address_rule' => false,
                'value' => $data['days'],
                'compare_with' => 'day',
                'false_value' => false
            );
        }
        if ($data['date_start'] != "" && $data['date_end']) {
            $rules['date'] = array(
                'type' => 'in_between',
                'product_rule' => false,
                'address_rule' => false,
                'start' => $data['date_start'],
                'end' => $data['date_end'],
                'compare_with' => 'date'
            );
        }
        if ($data['time_start'] != "" && $data['time_end']) {
            $valid_hours = array();
            $time_start = (int)$data['time_start'];
            $time_end = (int)$data['time_end'];

            if ($time_start <= $time_end) {
               for ($i = $time_start; $i < $time_end ; $i++) { 
                  $valid_hours[] = $i;
               }
            } else {
               for ($i = 0; $i < $time_end ; $i++) { 
                  $valid_hours[] = $i;
               }
               for ($i = $time_start; $i <= 23 ; $i++) { 
                  $valid_hours[] = $i;
               }
            }

            $rules['time'] = array(
                'type' => 'in_array',
                'product_rule' => false,
                'address_rule' => false,
                'value' => $valid_hours,
                'compare_with' => 'time',
                'false_value' => false
            );
        }
        /* Special rule if only ending time and date range set */
        if ($data['date_start'] != "" && $data['date_end'] && !$data['time_start'] && $data['time_end']) {
            $valid_hours = array();
            $time_start = 0;
            $time_end = (int)$data['time_end'];
            for ($i = $time_start; $i < $time_end ; $i++) { 
                  $valid_hours[] = $i;
            }
            $rules['date_time'] = array(
                'type' => 'in_array_not_equal',
                'product_rule' => false,
                'value' => $valid_hours,
                'compare_with' => 'time',
                'not_equal_value' => $data['date_end'],
                'not_equal_with' => 'date',
                'false_value' => false
            );
        }
        if ($data['max_length'] || $data['max_width'] || $data['max_height']) {
            $rules['package_dimension'] = array(
                'type' => 'function',
                'func' => '_validateDimension',
                'product_rule' => false,
                'address_rule' => false,
                'value' => array('length' => (float)$data['max_length'], 'width' => (float)$data['max_width'], 'height' => (float)$data['max_height']),
                'compare_with' => 'products',
                'rule_type' => 'max_dimension',
                'false_value' => false
            );
        }
        if ($data['rate_type'] != 'sub'
            && $data['rate_type'] != 'total'
            && $data['rate_type'] != 'total_coupon'
            && $data['rate_type'] != 'grand_shipping'
            && $data['rate_type'] != 'grand'
            && $data['order_total_start'] != "" 
            && (float)$data['order_total_end']) {
                $rules['additional_total'] = array(
                    'type' => 'in_between',
                    'product_rule' => false,
                    'address_rule' => false,
                    'start' => (float)$data['order_total_start'],
                    'end' => (float)$data['order_total_end'],
                    'compare_with' => 'total'
                );
        }
        if ($data['rate_type'] != 'weight'
            && $data['weight_start'] != ""
            && (float)$data['weight_end']) {
                $rules['additional_weight'] = array(
                    'type' => 'in_between',
                    'product_rule' => false,
                    'address_rule' => false,
                    'start' => (float)$data['weight_start'],
                    'end' => (float)$data['weight_end'],
                    'compare_with' => 'weight'
                );
        }
        if ($data['rate_type'] != 'quantity'
            && $data['quantity_start'] != ""
            && (int)$data['quantity_end']) {
                $rules['additional_qunatity'] = array(
                    'type' => 'in_between',
                    'product_rule' => false,
                    'address_rule' => false,
                    'start' => (int)$data['quantity_start'],
                    'end' => (int)$data['quantity_end'],
                    'compare_with' => 'quantity'
                );
        }
        /* Hooking fields */
        if ($data['hook']) {
            foreach ($data['hook'] as $key => $value) {
                $rules['option'] = array(
                    'type' => 'function',
                    'func' => 'hook_' . $key,
                    'product_rule' => false,
                    'address_rule' => false,
                    'value' => $value,
                    'false_value' => false,
                    'rule_type' => $key,
                    'compare_with' => 'products'
                );
            }
        }
        return $rules;
    }
    private function _findRawRate($data) {
        $operators= array('+','-','/','*');
        $rates = array();
        $rates['type'] = $data['rate_type'];
        $rates['equation'] = $data['equation'];
        $rates['equation_specified_param'] = (strpos($data['equation'], 'PerProductRule') !== false);
        $rates['final'] = $data['rate_final'];
        $rates['percent_of'] = $data['rate_percent'];
        $rates['overrule'] = !!$data['dimensional_overfule'];
        $rates['factor'] = $data['dimensional_factor'];
        $rates['additional'] = array();
        $rates['cart_adjust'] = array();
        $rates['price_adjust'] = array();

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
                if ($data['rate_type'] == 'product') {
                    $ranges[$product_id] = array('percent' => $percent, 'value' => $value, 'block' => $block, 'type' => $type);
                } else {
                    $ranges[] = array('start' => round($start, 3), 'end' => round($end, 3), 'percent' => $percent, 'value' => $value, 'block' => $block, 'partial' => $partial);
                }
            }
            $rates['ranges'] = $ranges;
        }
      
       /* Other price parameters */
       if ($data['cart_adjust']) {
            $operator = substr(trim($data['cart_adjust']),0,1);
            $operator = in_array($operator,$operators) ? $operator : '+';
            $adjust = ltrim($data['cart_adjust'], '+-*/');
            if (substr($adjust, -1) == '%') {
                $adjust = rtrim($adjust,'%');
                $rates['cart_adjust']['percent'] = true;
                $rates['cart_adjust']['value'] = (float)$adjust / 100;
                $rates['cart_adjust']['operator'] = $operator;
            } else {
                $rates['cart_adjust']['percent'] = false;
                $rates['cart_adjust']['value'] = (float)$adjust;
                $rates['cart_adjust']['operator'] = $operator;
            }
        }
        if ($data['rate_min'] && $data['rate_type'] != 'flat') {
             $rate_min = $data['rate_min'];
             $rates['price_adjust']['min'] = array();
             if (substr($rate_min, -1) == '%') {
                $rate_min = rtrim($rate_min,'%');
                $rates['price_adjust']['min']['percent'] = true;
                $rates['price_adjust']['min']['value'] = (float)$rate_min / 100;
             } else {
                $rates['price_adjust']['min']['percent'] = false;
                $rates['price_adjust']['min']['value'] = (float)$rate_min;
             }
        }
        if ($data['rate_max'] && $data['rate_type'] != 'flat') {
             $rate_max = $data['rate_max'];
             $rates['price_adjust']['max'] = array();
             if (substr($rate_max, -1) == '%') {
                $rate_max = rtrim($rate_max,'%');
                $rates['price_adjust']['max']['percent'] = true;
                $rates['price_adjust']['max']['value'] = (float)$rate_max / 100;
             } else {
                $rates['price_adjust']['max']['percent'] = false;
                $rates['price_adjust']['max']['value'] = (float)$rate_max;
             }
        }
        if ($data['rate_add'] && $data['rate_type'] != 'flat') {
            $modifier = $data['rate_add'];
            $rates['price_adjust']['modifier'] = array();
            $operator = substr(trim($modifier),0,1);
            $operator = in_array($operator,$operators) ? $operator : '+';
            $modifier = ltrim($modifier, '+-*/');
            if (substr($modifier, -1) == '%') {
                $modifier = rtrim($modifier,'%');
                $rates['price_adjust']['modifier']['percent'] = true;
                $rates['price_adjust']['modifier']['value'] = (float)$modifier / 100;
                $rates['price_adjust']['modifier']['operator'] = $operator;
            } else {
                $rates['price_adjust']['modifier']['percent'] = false;
                $rates['price_adjust']['modifier']['value'] = (float)$modifier;
                $rates['price_adjust']['modifier']['operator'] = $operator;
            }
        }

        if ($data['additional']) {
             $additional = $data['additional'];
             if (substr($additional, -1) == '%') {
                $additional = rtrim($additional,'%');
                $rates['additional']['percent'] = true;
                $rates['additional']['value'] = (float)$additional / 100;
             } else {
                $rates['additional']['percent'] = false;
                $rates['additional']['value'] = (float)$additional;
             }
             $rates['additional']['block'] = (float)$data['additional_per'];
             $rates['additional']['max'] = (float)$data['additional_limit'];
        }
        return $rates;
    }

    private function _crucify($rules, $data, $product_and_or, $ingore_product_rule = false, $only_address_rule = false) {
            $status = true;
            $product_status = false;
            $product_rules = array();
            $debugging = array();

            foreach ($rules as $name => $rule) {
                if ($only_address_rule && !$rule['address_rule']) {
                  continue;
                }
                if ($ingore_product_rule && $rule['product_rule']) {
                  continue;
                }

                $_debug_hint = $rule['compare_with'] !== 'products' ? $data[$rule['compare_with']] : '';
                $debug_value = is_array($_debug_hint) ? implode(',', $_debug_hint) : $_debug_hint;

                if ($rule['type'] == 'in_array') {
                    if (in_array($data[$rule['compare_with']], $rule['value']) === (boolean)$rule['false_value']) {
                        $debugging[] = $name . '('.$debug_value.')';
                        $status = false;
                        break;
                    }
                }
                if ($rule['type'] == 'intersect') {
                    if ((boolean)$this->array_intersect_faster($data[$rule['compare_with']], $rule['value']) === (boolean)$rule['false_value']) {
                        $debugging[] = $name . '('.$debug_value.')';
                        $status = false;
                        break;
                    }
                }
                if ($rule['type'] == 'in_between') {
                    if ($data[$rule['compare_with']] < $rule['start'] ||  $data[$rule['compare_with']] > $rule['end']) {
                        $debugging[] = $name . '('.$debug_value.')';
                        $status = false;
                        break;
                    }
                }
                if ($rule['type'] == 'in_array_not_equal') {
                    if ($data[$rule['not_equal_with']] == $rule['not_equal_value'] && in_array($data[$rule['compare_with']], $rule['value']) === (boolean)$rule['false_value']) {
                        $debugging[] = $name . '('.$debug_value.')';
                        $status = false;
                        break;
                    }
                }
                if ($rule['type'] == 'function') {
                    if (!property_exists($this, $rule['func']) && !method_exists($this, $rule['func'])) {
                        continue;
                    }
                    $_return = $this->{$rule['func']}($rule['value'], $data[$rule['compare_with']], $rule['rule_type']);
                    if ($rule['product_rule'] && $product_and_or) {
                        $product_status |= $_return;
                        $product_rules[$name] = $_return;
                    } else {
                        if ($_return === (boolean)$rule['false_value']) {
                            $debugging[] = $name . '('.$debug_value.')';
                            $status = false;
                            break;
                        }
                    }
                }
            }
            /* check or_mode for product rules */
            if ($product_and_or && $product_rules && !$product_status) {
                $status = false;
                foreach ($product_rules as $key => $value) {
                    if (!$value) {
                        $debugging[] = $key;
                    }
                }
            }

            return array(
              'status' => $status,
              'debugging' => $debugging
            );
    }
    private function _replenishAddress($address) {
        if (!isset($address['zone_id'])) $address['zone_id'] = '';
        if (!isset($address['country_id'])) $address['country_id'] = '';
        if (!isset($address['city'])) $address['city'] = '';
        if (!isset($address['postcode'])) $address['postcode'] = '';

        $fields = array('zone_id', 'country_id', 'city', 'postcode');
        /* Xshippingpro estimator */
        if (isset($this->request->post['_xestimator'])) {
            $_xestimator = $this->request->post['_xestimator'];
            foreach ($fields as $field) {
                if (!$address[$field]
                  && isset($_xestimator[$field])
                  && $_xestimator[$field]) {
                     $address[$field] = $_xestimator[$field];
                }
            }
        }

        $sessions = array('shipping_address', 'payment_address');
        foreach ($sessions as $key) {
            foreach ($fields as $field) {
                if (!$address[$field]
                  && isset($this->session->data[$key])
                  && isset($this->session->data[$key][$field])
                  && $this->session->data[$key][$field]) {
                     $address[$field] = $this->session->data[$key][$field];
                }
            }
        }

        /* Still country emptry, set default one */
        if (!$address['country_id']) {
            $address['country_id'] = $this->config->get('config_country_id');
        }
        /* all option has failed for postal and city, lets fetch from address book */
        if (!$address['postcode'] && !$address['city'] && $this->customer->isLogged()) {
            $this->load->model('account/address');
            $customer_address = $this->model_account_address->getAddress($this->customer->getAddressId());
            if ($customer_address) {
                $address['postcode'] = $customer_address['postcode'];
                $address['city'] = $customer_address['city'];
            }
        }
        $address['city'] = strtolower(trim($address['city']));
        $address['postcode'] = strtolower(trim($address['postcode']));
        return $address;
    }

    private function _getCommonParams($address) {
        $param = array();
        if (isset($_POST['customer_group_id']) && $_POST['customer_group_id']) {
            $customer_group_id = $_POST['customer_group_id'];
        }
        elseif (isset($_GET['customer_group_id']) && $_GET['customer_group_id']) {
            $customer_group_id = $_GET['customer_group_id'];
        }
        elseif ($this->customer->isLogged()) {
            $customer_group_id = $this->customer->getGroupId();
        } elseif (isset($this->session->data['customer']) && isset($this->session->data['customer']['customer_group_id']) && $this->session->data['customer']['customer_group_id']) {
            $customer_group_id = $this->session->data['customer']['customer_group_id'];     
        } else {
            $customer_group_id = 0;
        }

        $store_id = $this->config->get('config_store_id');
        $store_id = isset($this->request->post['store_id']) ? $this->request->post['store_id'] : $store_id;
        $store_id = isset($this->request->get['store_id']) ? $this->request->get['store_id'] : $store_id;

        $payment_method = isset($this->session->data['payment_method']['code'])?$this->session->data['payment_method']['code']:'';
        if(isset($this->session->data['default']['payment_method']['code'])) $payment_method = $this->session->data['default']['payment_method']['code'];


        /* currency */
        $currency_code = isset($this->session->data['currency']) ? $this->session->data['currency'] : $this->config->get('config_currency');
        $currency_id = $this->currency->getId($currency_code);

        /* Coupon code */
        $coupon_code = '';
        if (isset($this->session->data['default']['coupon']) && $this->session->data['default']['coupon']) {
            $coupon_code = $this->session->data['default']['coupon'];
        }
        if (isset($this->session->data['coupon']) && $this->session->data['coupon']) {
            $coupon_code = $this->session->data['coupon'];
        }
        if ($coupon_code) {
            $coupon_code = strtolower($coupon_code);
        }
        $param['customer_id'] = $this->customer->getId();
        $param['store_id'] = $store_id;
        $param['customer_group_id'] = $customer_group_id;
        $param['payment_method'] = $payment_method;
        $param['coupon_code'] = $coupon_code;
        $param['city'] = $address['city'];
        $param['country_id'] = $address['country_id'];
        $param['zone_id'] = $address['zone_id'];
        $param['postcode'] = $address['postcode'];
        $param['currency_id'] = $currency_id;
        $param['time'] = date('G');
        $param['date'] = date('Y-m-d');
        $param['day'] = date('w');
        return $param;
    }

    private function _getApplicableProducts($rules, $cart_data) {
        $_applicable = array(
            'category' => $cart_data['category'],
            'product' => $cart_data['product'],
            'manufacturer' => $cart_data['manufacturer'],
            'option' => $cart_data['option'],
            'location' => $cart_data['location']
        );

        foreach ($_applicable as $key => $value) {
            if (isset($rules[$key])) {
                if ($rules[$key]['rule_type'] == 5 || $rules[$key]['rule_type'] == 7) {
                    $_applicable[$key] = $rules[$key]['value'] ? array_diff($cart_data[$key], $rules[$key]['value']) : $rules[$key]['value'];
                } else {
                    $_applicable[$key] = $rules[$key]['value'];
                }
            }
        }
        $_applicable['no_category'] = count($_applicable['category']);
        $_applicable['no_manufacturer'] = count($_applicable['manufacturer']);
        $_applicable['no_location'] = count($_applicable['location']);
        return $_applicable;
    }

    private function _calVirtualWeight($cart_products, $factor_value, $over_rule) {
        $dimensional = 0;
        $volumetric = 0;
        $product_dimensional = array();
        $product_volumetric = array();

        foreach ($cart_products as $product) {
            $single_dimensional_weight = ($product['volume'] / $factor_value) * $product['weight'];
            $single_volumetric_weight = ($product['volume'] / $factor_value);

            if ($over_rule && $single_dimensional_weight < $product['weight']) {
                $single_dimensional_weight = $product['weight'];
            }
            if ($over_rule && $single_volumetric_weight < $product['weight']) {
                $single_volumetric_weight = $product['weight'];
            }
            $dimensional += $single_dimensional_weight;
            $volumetric += $single_volumetric_weight;
            $product_dimensional[$product['product_id']] = $single_dimensional_weight;
            $product_volumetric[$product['product_id']] = $single_volumetric_weight;
        }
        return array(
            'dimensional' => $dimensional,
            'volumetric' => $volumetric,
            'product_dimensional' => $product_dimensional,
            'product_volumetric' => $product_volumetric
        );
    }

    private function _getMethodSpecificData($need_specified, $rules, $applicable_cart, $cart_data, $product_or) {
        $_method = array();
        $_method['quantity'] = $need_specified ? 0 : $cart_data['quantity'];
        $_method['weight'] = $need_specified ? 0 : $cart_data['weight'];
        $_method['total'] = $need_specified ? 0 : $cart_data['total'];
        $_method['sub'] = $need_specified ? 0 : $cart_data['sub'];
        $_method['volume'] = $need_specified ? 0 : $cart_data['volume'];
        $_method['dimensional'] = $need_specified ? 0 : $cart_data['dimensional'];
        $_method['volumetric'] = $need_specified ? 0 : $cart_data['volumetric'];
        $_method['products'] = $need_specified ? array() : $cart_data['products'];
        $_method['no_category'] = $need_specified ? $applicable_cart['no_category'] : $cart_data['no_category'];
        $_method['no_manufacturer'] = $need_specified ? $applicable_cart['no_manufacturer'] : $cart_data['no_manufacturer'];
        $_method['no_location'] = $need_specified ? $applicable_cart['no_location'] : $cart_data['no_location'];

        if ($need_specified) {
            foreach($cart_data['products'] as $product) {
                $count_on = !$product_or;
                $force_off = !$product_or;

                foreach ($rules as $key => $rule) {
                    if (!$rule['product_rule']) continue;
                    $is_valid = ($key == 'category' || $key == 'option') ? $this->array_intersect_faster($product[$key],$applicable_cart[$key]) : in_array($product[$key], $applicable_cart[$key]);
                    $count_on = $product_or ? ($count_on | $is_valid) : ($count_on & $is_valid);
                    
                    /* additional check for rule 5 and 7 i.e except ...*/
                    if ($rule['rule_type']==5 || $rule['rule_type']==7) {
                        $is_valid = ($key == 'category' || $key == 'option') ? $this->array_intersect_faster($product[$key], $rule['value']) : in_array($product[$key], $rule['value']);
                        $force_off = $product_or ? ($force_off | $is_valid) : ($force_off & $is_valid);
                    } else {
                        $force_off = false;
                    }
                }
                
                if (!$product_or && (!$count_on || $force_off)) continue;
                if ($product_or && !$count_on && $force_off) continue;

                $_method['products'][] = $product;
                $_method['quantity'] += $product['quantity'];
                $_method['weight'] += $product['weight'];
                $_method['total'] += $product['total_with_tax'];
                $_method['sub'] += $product['total']; 
                $_method['volume'] += isset($product['volume']) ? $product['volume'] : 0;
                $_method['dimensional'] += isset($cart_data['product_dimensional'][$product['product_id']]) ? $cart_data['product_dimensional'][$product['product_id']] : 0;
                $_method['volumetric'] += isset($cart_data['product_volumetric'][$product['product_id']]) ? $cart_data['product_volumetric'][$product['product_id']] : 0; 
            }
       }

       $_method['total_coupon'] = ($_method['total'] + $cart_data['coupon'] + $cart_data['reward']);
       $_method['grand'] = $cart_data['grand'];
       $_method['grand_shipping'] = $cart_data['grand_shipping'];
       
       /* Shipping cost would be added later */
       $_method['sub_shipping'] = $_method['sub'];
       $_method['total_shipping'] = $_method['total'];
       $_method['shipping'] = 0;
       $_method['distance'] = $cart_data['distance'];
       return $_method;
    }

    private function array_intersect_faster($array1, $array2) {
        $is_found = false;
        foreach ($array1 as $key) {
           if (in_array($key, $array2)) {
                $is_found = true;
                break;
            }
        }
        return $is_found;
    }
    private function tiniestCalculator($num1, $num2, $operator) {
        if ($operator == '+') return $num1 + $num2;
        if ($operator == '-') return $num1 - $num2;
        if ($operator == '*') return $num1 * $num2;
        if ($operator == '/') {
           if (!$num2) $num2 = 1;
           return $num1 / $num2 ;
        }
    }
    private function adjustValue($adjust_rate, $percent_of, $value) {
        $amount = $adjust_rate['percent'] ? ($adjust_rate['value'] * $percent_of) : $adjust_rate['value'];
        return $this->tiniestCalculator($value, $amount, $adjust_rate['operator']);
    }
    private function hideMethodsOnActive($quote_data, $hide_list, &$debugging) {
        if($hide_list) {
            $truncated = array();
            foreach ($quote_data as $key => $value) {
               $tab_id = $value['tab_id'];
               if (isset($hide_list[$tab_id]) && $hide_list[$tab_id]) {
                    $method_hide_list = $hide_list[$tab_id]['hide'];
                    foreach($method_hide_list as $hide_id) {
                        if (isset($quote_data['xshippingpro'.$hide_id])) {
                            $truncated[] = $hide_id;
                            /* Remove it from hide_list so it can not cancel each other */
                            if (isset($hide_list[$hide_id])) {
                                unset($hide_list[$hide_id]);
                            }
                            $debugging[] = array('name' => $quote_data['xshippingpro'.$hide_id]['display'],'filter' => array('Hidden by '.$hide_list[$tab_id]['display'].' when it was active'),'index' => $hide_id);
                        }
                    }
               }
            }
            /* Finally remove truncated ID */
            foreach ($truncated as $tab_id) {
                unset($quote_data['xshippingpro'.$tab_id]);
            }
        }
        return $quote_data;
    }
    private function hideMethodsOnInactive($quote_data, $hide_list, &$debugging) {
        if($hide_list) {
            foreach($hide_list as $hide_by => $hide) {
                foreach($hide['hide'] as $tab_id) {
                    if(isset($quote_data['xshippingpro'.$tab_id])) {
                        $debugging[]=array('name' => $quote_data['xshippingpro'.$tab_id]['display'],'filter' => array('Hidden by '.$hide['display'].' when it was inactive'),'index' => $tab_id);
                        unset($quote_data['xshippingpro'.$tab_id]);
                    }
                }  
            }
        }
        return $quote_data;
    }

    private function getProducts() {
        $product_id = isset($this->request->post['_xestimator']) && isset($this->request->post['_xestimator']['product_id']) ? $this->request->post['_xestimator']['product_id'] : 0;
        if ($product_id) {
            $this->load->model('catalog/product');
            $product_info = $this->model_catalog_product->getProduct($product_id);
            if ($product_info) {
                $quantity = isset($this->request->post['quantity']) && $this->request->post['quantity'] ? $this->request->post['quantity'] : 1;
                $quantity = isset($this->request->get['quantity']) && $this->request->get['quantity'] ? $this->request->get['quantity'] : $quantity;
                $product_info['quantity'] = $quantity;
                $price = $product_info['price'];
                if ((float)$product_info['special'])  {
                   $price = $product_info['special'];
                }
                $option_price = 0;
                $option_weight = 0;
                if(isset($this->request->post['option']) && is_array($this->request->post['option'])) {
                    foreach($this->request->post['option'] as $product_option_value_ids) {
                        if ($product_option_value_ids) {
                            if (!is_array($product_option_value_ids)) {
                                $product_option_value_ids = array($product_option_value_ids);
                            }
                            foreach($product_option_value_ids as $product_option_value_id) {
                                $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_option_value WHERE product_option_value_id = '" . (int)$product_option_value_id . "'");
                                if ($query->row) {
                                    if ($query->row['price_prefix']=='+') {
                                        $option_price += (float)$query->row['price'];
                                        $option_weight += (float)$query->row['weight'];
                                    }
                                    if ($query->row['price_prefix']=='-') {
                                        $option_price -= (float)$query->row['price'];
                                        $option_weight -= (float)$query->row['weight'];
                                    }
                                }
                            }
                        }
                    }
                }
                $product_info['price'] = ($price + $option_price);
                $product_info['total'] = ($price + $option_price) * $quantity;
                $product_info['weight'] = ($product_info['weight'] + $option_weight) * $quantity;
            }
            return array($product_info);
        }
        return $this->cart->getProducts();
    }

    private function getEquationValue($equation, $_cart_data, $method_specific_data, $quote_data, $percent_of, $shipping_cost = 0, $modifier_amount = 0) {
       
        $placholder = array(
            '{subTotal}',
            '{subTotalWithTax}',
            '{quantity}',
            '{weight}',
            '{volume}',
            '{noOfCategory}', 
            '{noOfManufacturer}', 
            '{noOfLocation}',
            '{subTotalAsPerProductRule}',
            '{subTotalWithTaxAsPerProductRule}',
            '{quantityAsPerProductRule}',
            '{weightAsPerProductRule}',
            '{volumeAsPerProductRule}',
            '{couponValue}',
            '{rewardValue}',
            '{shipping}',
            '{modifier}',
            '{grandTotal}',
            '{grandBeforeShipping}',
            '{distance}',
            '{jan}',
            '{ean}',
            '%'
        );

        $replacer = array(
            $_cart_data['sub'],
            $_cart_data['total'],
            $_cart_data['quantity'],
            $_cart_data['weight'],
            $_cart_data['volume'],
            $_cart_data['no_category'],
            $_cart_data['no_manufacturer'],
            $_cart_data['no_location'],
            $method_specific_data['sub'],
            $method_specific_data['total'],
            $method_specific_data['quantity'],
            $method_specific_data['weight'],
            $method_specific_data['volume'],
            $_cart_data['coupon'],
            $_cart_data['reward'],
            $shipping_cost,
            $modifier_amount,
            $_cart_data['grand'],
            $_cart_data['grand_shipping'],
            $_cart_data['distance'],
            $_cart_data['jan'],
            $_cart_data['ean'],
            '*'.($percent_of/100)
        );

        if (preg_match('/minHeight|maxHeight|sumHeight|minWidth|maxWidth|sumWidth|minLength|maxLength|sumLength/', $equation)) {
            $placholder[] = '{minHeight}';
            $placholder[] = '{maxHeight}';
            $placholder[] = '{sumHeight}';
            $placholder[] = '{minWidth}';
            $placholder[] = '{maxWidth}';
            $placholder[] = '{sumWidth}';
            $placholder[] = '{minLength}';
            $placholder[] = '{maxLength}';
            $placholder[] = '{sumLength}';
            
            $minHeight = $minWidth = $minLength = PHP_INT_MAX;
            $maxHeight = $maxWidth = $maxLength = PHP_INT_MIN;
            $sumHeight = $sumWidth = $sumLength = 0;
            foreach ($method_specific_data['products'] as $product) {
                $sumHeight += ($product['height_self'] * $product['quantity']);
                if ($minHeight > $product['height_self']) {
                    $minHeight = $product['height_self'];
                }
                if ($maxHeight < $product['height_self']) {
                    $maxHeight = $product['height_self'];
                }
                $sumWidth += ($product['width_self'] * $product['quantity']);
                if ($minWidth > $product['width_self']) {
                    $minWidth = $product['width_self'];
                }
                if ($maxWidth < $product['width_self']) {
                    $maxWidth = $product['width_self'];
                }
                $sumLength += ($product['length_self'] * $product['quantity']);
                if ($minLength > $product['length_self']) {
                    $minLength = $product['length_self'];
                }
                if ($maxLength < $product['length_self']) {
                    $maxLength = $product['length_self'];
                }
            }
            $replacer[] = $minHeight;
            $replacer[] = $maxHeight;
            $replacer[] = $sumHeight;
            $replacer[] = $minWidth;
            $replacer[] = $maxWidth;
            $replacer[] = $sumWidth;
            $replacer[] = $minLength;
            $replacer[] = $maxLength;
            $replacer[] = $sumLength;
        }
        
        /* append other shipping method cost as placeholders */
        foreach ($quote_data as $value) {
            $placholder[] = '{shipping'.$value['tab_id'].'}';
            $replacer[] = $value['cost'];
        }

        /* xfeepro value */
        foreach ($_cart_data['xfeepro'] as $code => $value) {
            $placholder[] = '{'.$code.'}';
            $replacer[] = $value;
        }
        $shipping_keyword = strpos($equation, '{shipping}') === false ? false : true;
        $equation = str_replace($placholder, $replacer, $equation);

        /* Any placeholders */
        if (preg_match('/anyProduct/', $equation)) {
            $any_placeholders = array('{anyProductWidth}', '{anyProductHeight}', '{anyProductLength}', '{anyProductWeight}', '{anyProductStock}', '{anyProductPrice}', '{shipping}');
            $_equation = $equation;
            $cost = 0;
            foreach ($method_specific_data['products'] as $product) {
                $any_replacers = array($product['width_self'], $product['height_self'], $product['length_self'], $product['weight'], $product['stock'], $product['price_self'], $cost);
                $equation = str_replace($any_placeholders, $any_replacers, $_equation);
                /* Removing unwanted placeholder */
                if (strpos($equation, '{') !== false) {
                    $equation = preg_replace('/{.*?}/', 0, $equation);
                }
                $cost += (float)$this->calculate_string($equation);
                // either cumulate or stop depending on present of placeholder {shipping}
                if ($cost >= 0 && !$shipping_keyword) {
                    break;
                }
            }
        } else {
            /* Removing unwanted placeholder */
            if (strpos($equation, '{') !== false) {
                $equation = preg_replace('/{.*?}/', 0, $equation);
            }
            $cost = (float)$this->calculate_string($equation);
        }
        return $cost;
    }

    private function getDefaultPaymentMethod($address, $total) {
        $this->load->model($this->ocm->setting_ext . '/extension');
        $extension = $this->{'model_' . $this->ocm->setting_ext . '_extension'};
        $results = $extension->getExtensions('payment');
        foreach ($results as $result) {
            if ($this->ocm->getConfig($result['code'] . '_status', 'payment')) {
                $this->load->model('extension/payment/' . $result['code']);
                $method = $this->{'model_extension_payment_' . $result['code']}->getMethod($address, $total);
                if ($method) {
                    $method_data[$result['code']] = $method;
                }
            }
        }
        $sort_order = array();
        foreach ($method_data as $key => $value) {
            $sort_order[$key] = $value['sort_order'];
        }
        array_multisort($sort_order, SORT_ASC, $method_data);
        $method_data = array_shift($method_data);
        return isset($method_data['code']) ? $method_data['code'] : '';
    }
    private function getDistance($dest) {
        if (!isset($this->session->data['xmap_cache'])) {
            $this->session->data['xmap_cache'] = array();
        }
        $crc32 = crc32($dest);
        if (isset($this->session->data['xmap_cache'][$crc32])) {
            return $this->session->data['xmap_cache'][$crc32];
        }
        $distance = 0;
        $xshippingpro_debug = $this->ocm->getConfig('xshippingpro_debug', $this->mtype);
        $xshippingpro_map_api = $this->ocm->getConfig('xshippingpro_map_api', $this->mtype);
        $store_geocode = $this->config->get('config_geocode');

        $url = 'https://maps.googleapis.com/maps/api/distancematrix/json?origins='.rawurlencode($store_geocode).'&destinations='.rawurlencode($dest).'&key=' . $xshippingpro_map_api;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_PROXYPORT, 3128);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $response = curl_exec($ch);
        curl_close($ch);
        $response = json_decode($response, true);
        if (is_array($response) && $response['status'] == 'OK' && $response['rows']) {
            $distance = isset($response['rows'][0]['elements'][0]['distance']) ? ($response['rows'][0]['elements'][0]['distance']['value'] / 1000) : 0;
        }

        if ($xshippingpro_debug) {
            $this->log->write('Xshippinpgor Map API (URL: '.$url.') (Distance: '.$distance.') and Response: '. print_r($response, true));
        }
        $this->session->data['xmap_cache'][$crc32] = $distance;
        return $distance;
    }

    private function getCSS($xshippingpro_estimator) {
        $css = '<style type="text/css">
                    .xshippingpro-box {
                        background: #f5f5f5;
                        margin-bottom: 10px;
                    }
                    .popup-quickview .xshippingpro-box {
                        display: none;
                    }
                    .xshippingpro-box .shipping-header {
                        font-size: 15px;
                        padding: 7px 10px;
                    }
                    .xshippingpro-box .shipping-fields {
                        padding: 0px 8px 8px 8px;
                    }
                    .xshippingpro-box .shipping-field {
                        margin-bottom: 5px;
                    }
                    .xshippingpro-box .xshippingpro-error {
                        border: 1px solid #fb6969;
                    }
                    .xshippingpro-quotes {
                        background: #f5f5f5;
                        padding: 5px 10px;
                        margin-bottom: 10px;
                    }
                    .xshippingpro-quotes .xshippingpro-quote {
                        margin-bottom: 5px;
                    }
                    .xshippingpro-quotes .xshippingpro-quote:last-child {
                        margin-bottom: 0px;
                    }
                    .xshippingpro-option-error {
                        color: #dc4747;
                    }
                    .xshippingpro-options {
                        margin: 5px 0px;
                    }
                    .xshippingpro-desc {
                        color: #999999;
                        font-size: 11px;
                        display:block
                    }
                    .xshippingpro-logo {
                        margin-right: 3px; 
                        vertical-align: middle;
                        max-height: 50px;
                    }
                    /* Journal 3 laytout for suboption */
                    .quick-checkout-wrapper .radio {
                        flex-direction: column;
                        align-items: start;
                    }
            </style>';

        if ($xshippingpro_estimator && $xshippingpro_estimator['css']) {
          $css .= '<style type="text/css">'.$xshippingpro_estimator['css'].'</style>';
        }
        return $css;
    }

    private function getJS($xshippingpro_estimator) {
        $this->load->language($this->ext_path);
        $selectors = array();
        $selectors['estimator'] = '#product';
        $selectors['shipping_error'] = '#content';
        $meta = array();
        $meta['country_id'] = !$this->ocm->isCheckoutPage() ? $this->config->get('config_country_id') : false;
        $meta['product_id'] = isset($this->request->get['product_id']) ? $this->request->get['product_id'] : 0;
        if ($xshippingpro_estimator && isset($xshippingpro_estimator['fields'])) {
            if (in_array('country', $xshippingpro_estimator['fields'])) {
                $meta['country'] = true;
            }
            if (in_array('zone', $xshippingpro_estimator['fields'])) {
                $meta['zone'] = true;
            }
            if (in_array('postal', $xshippingpro_estimator['fields'])) {
                $meta['postal'] = true;
            }
            if (isset($xshippingpro_estimator['selector']) && $xshippingpro_estimator['selector']) {
                $selectors['estimator'] = $xshippingpro_estimator['selector'];
            }
        }
        $url = array(
            'country' => VERSION >=  '2.1.0.1' ? 'index.php?route=extension/total/shipping/country' : 'index.php?route=checkout/shipping/country',
            'estimate' => 'index.php?route=extension/shipping/xshippingpro/estimate_shipping'
        );

        $lang = array();
        $lang['header'] = $this->language->get('xshippingpro_estimator_header');
        $lang['country'] = $this->language->get('xshippingpro_estimator_country');
        $lang['zone'] = $this->language->get('xshippingpro_estimator_zone');
        $lang['postal'] = $this->language->get('xshippingpro_estimator_postal');
        $lang['no_data'] = $this->language->get('xshippingpro_estimator_no_data');
        $lang['btn'] = $this->language->get('xshippingpro_estimator_button');
        $lang['select'] = $this->language->get('xshippingpro_select');
        $lang['error'] = $this->language->get('xshippingpro_select_error');

        $_xshippingpro = array();
        $_xshippingpro['url'] = $url;
        $_xshippingpro['meta'] = $meta;
        $_xshippingpro['lang'] = $lang;
        $_xshippingpro['selectors'] = $selectors;
        $_xshippingpro['sub_options'] = false;
        $_xshippingpro['desc'] = false;
        $_xshippingpro['logo'] = false;
        $_xshippingpro['is_checkout'] = $this->ocm->isCheckoutPage() ? true : false;
        if ($this->ocm->isCheckoutPage()) {
           $sub_options = $this->getSubOptions();
           $desc_logo = $this->getShippingDesc();
           if ($sub_options) {
              $_xshippingpro['sub_options'] = $sub_options;
           }
           if ($desc_logo['desc']) {
              $_xshippingpro['desc'] = $desc_logo['desc'];
           }
           if ($desc_logo['logo']) {
              $_xshippingpro['logo'] = $desc_logo['logo'];
           }
           $_xshippingpro['city'] = $desc_logo['city'];
           $_xshippingpro['payment'] = $desc_logo['payment'];
        }

        if (!$this->ocm->isCheckoutPage() && isset($meta['country'])) {
            $this->load->model('localisation/country');
            $_xshippingpro['country'] = $this->model_localisation_country->getCountries();
        }
        $js = '<script type="text/javascript">';
        $js .= 'var _xshippingpro = '.json_encode($_xshippingpro).';';
        $js .= '</script>';
        return $js;
    }
    public function getScript() {
        $xshippingpro_estimator =  $this->ocm->getConfig('xshippingpro_estimator', $this->mtype);
        $shipping_xshippingpro = $this->ocm->getConfig('xshippingpro_status', $this->mtype);
        $product_id = isset($this->request->get['product_id']) ? $this->request->get['product_id'] : 0;

        $store_id = $this->config->get('config_store_id');
        $estimator_on_store = true;
        if (isset($xshippingpro_estimator['store']) && !in_array($store_id, $xshippingpro_estimator['store'])) {
            $estimator_on_store = false;
        }
        $html = '';
        if ($shipping_xshippingpro && (($this->ocm->isCheckoutPage() && !$this->ocm->isCartPage()) || ($product_id && $estimator_on_store && isset($xshippingpro_estimator['status'])))) {
            $html .= $this->getCSS($xshippingpro_estimator);
            $html .= $this->getJS($xshippingpro_estimator);
            $html .= '<script src="catalog/view/javascript/xshippingpro.min.js?v=1.0.3" defer type="text/javascript"></script>';
        }
        return $html;
    }
    /* HOOK METHOD HERE */
    /* must start with hook_
    public function hook_custom_field($value, $cart_products, $name) {
        return true;
    } */
}