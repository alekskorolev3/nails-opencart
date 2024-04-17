<?php
class ControllerExtensionTotalXfeepro extends Controller {
     use OCM\Traits\Back\Controller\Common;
     use OCM\Traits\Back\Controller\Crud;
     use OCM\Traits\Back\Controller\Product;
     use OCM\Traits\Back\Controller\Util;
     private $ext_path;
     private $ext_key;
     private $error = array();
     private $ocm;
     private $meta = array(
        'id'       => '14078',
        'type'     => 'total',
        'name'     => 'xfeepro',
        'path'     => 'extension/total/',
        'title'    => 'X-Feepro',
        'version'  => '3.1.0',
        'ocmod'    => false
    );
    /* Config with default values  Special keyword __LANG__ denotes array of languages e.g 'name' => array('__LANG__' => 'xyz') */
    private $setting = array(
        'xfeepro_status'     => '',
        'xfeepro_sort_order' => '',
        'xfeepro_map_api'    => '',
        'xfeepro_group'      => '',
        'xfeepro_group_name' => '',
        'xfeepro_debug'      => ''
    );
    private $events = array();
    private $tables = array(
        'xfeepro' => array (
            array('name'=> 'sort_order', 'option' => 'int(8) NULL')
        )
    );
    public function __construct($registry) {
        parent::__construct($registry);
        $this->ocm = new OCM\Back($registry, $this->meta);
        $this->ext_path = $this->meta['path'] . $this->meta['name'];
        $this->ext_key = 'model_' . str_replace('/', '_', $this->ext_path);
    }
    public function index() {
        $this->load->language($this->ext_path);
        $ext_lang = $this->load->language($this->ext_path);
        $this->load->model($this->ext_path);
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/setting');

        /* Some help lang modificaiton */
        $ext_lang['help_time'] = sprintf($this->language->get('help_time'), date('h:i:s A'));
        $ext_lang['help_date'] = sprintf($this->language->get('help_date'), date('Y-m-d'));

        $data = array();
        $data = array_merge($data, $ext_lang);

        /* লাইসেন্স বেরিফিকেসন  */
        $data['_v'] = '';
        /* লাইসেন্স শেষ */
        $this->ocm->checkOCMOD();
        $this->upgrade();
        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            if(isset($this->request->post['action']) && $this->request->post['action'] == 'import') {
                $this->import();
                $this->response->redirect($this->ocm->url->getExtensionURL());
            }
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->ocm->url->getExtensionsURL());
        }

        $data['heading_title'] = $this->language->get('heading_title');
        $data['x_name'] = $this->meta['name'];
        $data['x_path'] = $this->meta['path'] . $this->meta['name'];

       if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }
        if (isset($this->session->data['warning'])) {
            $data['error_warning'] = $this->session->data['warning'];
            unset($this->session->data['warning']);
        }
        if (isset($this->session->data['success'])) {
            $data['success'] = $this->session->data['success'];
            unset($this->session->data['success']);
        } else {
            $data['success'] = '';
        }

        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->ocm->url->link('common/dashboard', '', true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->ocm->url->getExtensionsURL()
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->ocm->url->getExtensionURL()
        );

        $data['action'] = $this->ocm->url->getExtensionURL();
        $data['cancel'] = $this->ocm->url->getExtensionsURL();
        $data['export'] = $this->ocm->url->link('extension/total/xfeepro/export', '', true);

        $this->load->model('localisation/language');
        $languages = $this->model_localisation_language->getLanguages();
        $data['languages'] = $this->ocm->url->getLangImage($languages);
        $data['language_id'] = $this->config->get('config_language_id');

        $data['groups_count'] = 10;
        $data['method_data'] = $this->{$this->ext_key}->getData();

        /* All required options */
        $options = array();
        
        $this->load->model('localisation/tax_class'); 
        $tax_classes = $this->model_localisation_tax_class->getTaxClasses();
        array_unshift($tax_classes, array('tax_class_id' => 0,'name' => $this->language->get('text_none')));
        $options['tax_class_id'] = $this->ocm->form->getOptions($tax_classes, 'tax_class_id');

        $this->load->model('localisation/geo_zone');
        $geo_zones = $this->model_localisation_geo_zone->getGeoZones();
        $options['geo_zone'] = $this->ocm->form->getOptions($geo_zones, 'geo_zone_id');

        $this->load->model('setting/store');
        $stores = $this->model_setting_store->getStores();
        array_unshift($stores, array('store_id' => 0,'name' => $this->language->get('text_store_default')));
        $options['store'] = $this->ocm->form->getOptions($stores, 'store_id');

        $this->load->model('localisation/currency');
        $currencies = $this->model_localisation_currency->getCurrencies();
        $options['currency'] = $this->ocm->form->getOptions($currencies, 'currency_id');
      
        $cg_path = (VERSION >= '2.1.0.1') ? 'customer' : 'sale';
        $this->load->model($cg_path . '/customer_group');
        $customer_groups = $this->{'model_' . $cg_path . '_customer_group'}->getCustomerGroups();
        $customer_groups[] = array('customer_group_id' => 0, 'name' => $this->language->get('text_guest_checkout'));
        $options['customer_group'] = $this->ocm->form->getOptions($customer_groups, 'customer_group_id');

        $this->load->model('localisation/country');
        $countries = $this->model_localisation_country->getCountries();
        $options['country'] = $this->ocm->form->getOptions($countries, 'country_id');

        $options['payment'] = $this->ocm->misc->getPaymentMethods($data['language_id']);
        $options['shipping'] = $this->ocm->misc->getShippingMethods($data['language_id'], $geo_zones);

        $status_options = array('1' => $data['text_enabled'], '0' => $data['text_disabled']);
        $options['status'] = $options['debug'] = $this->ocm->form->getOptions($status_options, 'none');

        $inc_exc_options = array('inclusive' => $data['text_rule_inclusive'], 'exclusive' => $data['text_rule_exclusive']);
        $inc_exc_options = $this->ocm->form->getOptions($inc_exc_options, 'none');
        $options['city_rule'] = $options['coupon_rule'] = $options['postal_rule'] = $inc_exc_options; 

        $product_rule_options = array(
            '' => $data['text_any'],
            '6' => $data['text_ones_any'],
            '3' => $data['text_ones_any_with_other'],
            '4' => $data['text_ones_must'],
            '2' => $data['text_ones_must_with_other'],
            '5' => $data['text_ones_except'],
            '7' => $data['text_ones_except_with_other'] 
        );
        $product_rule_options = $this->ocm->form->getOptions($product_rule_options, 'none');
        $options['category'] = $options['product'] = $options['option'] = $options['manufacturer_rule'] = $options['location_rule'] = $product_rule_options; 

        $rate_type_options = array(
            'flat'  => $data['text_rate_flat']
        );
        $options['rate_type'] = $this->ocm->form->getOptions($rate_type_options, 'none');

        $percent_options = array(
            'sub'             => $data['text_percent_sub_total'],
            'total'           => $data['text_percent_total'],
            'grand'           => $data['text_grand_total'],
            'grand_wtax'      => $data['text_percent_grand_wo_tax'],
            'sub_shipping'    => $data['text_percent_sub_total_shipping'],
            'total_shipping'  => $data['text_percent_total_shipping'],
            'sub_shipping_plus'  => $data['text_percent_sub_total_shipping_plus'],
            'total_shipping_plus'  => $data['text_percent_total_shipping_plus'],
            'shipping'        => $data['text_percent_shipping']
        );
        $options['rate_percent'] = $this->ocm->form->getOptions($percent_options, 'none');

        $week_day_optios = array(
            '0' => $data['text_sunday'],
            '1' => $data['text_monday'],
            '2' => $data['text_tuesday'],
            '3' => $data['text_wednesday'],
            '4' => $data['text_thursday'],
            '5' => $data['text_friday'],
            '6' => $data['text_saturday']
        );
        $options['days'] = $this->ocm->form->getOptions($week_day_optios, 'none');

        $final_cost = array(
            'single' => $data['text_final_single'],
            'cumulative' => $data['text_final_cumulative']
        );
        $options['rate_final'] = $this->ocm->form->getOptions($final_cost, 'none');

        $modes = array(
            '0' => $data['text_mode_and'],
            '1' => $data['text_mode_or']
        );
        $options['product_or'] = $this->ocm->form->getOptions($modes, 'none');

        $group = array();
        $group[] = array(
            'name' => 'None',
            'value' => 0
        );
        for ($i=1; $i <= $data['groups_count']; $i++) {
            $group[] = array(
                'name' => 'Group' . $i,
                'value' => $i
            );
        }
        $options['group'] = $group;
        /* set form data */
        $this->ocm->form->setLangs($ext_lang)->setOptions($options);

        $group_modes = array(
            'no_group' => $this->language->get('text_no_grouping'),
            'lowest'   => $this->language->get('text_lowest'),
            'highest'  => $this->language->get('text_highest'),
            'average'  => $this->language->get('text_average'),
            'sum'      => $this->language->get('text_sum')
        );
        $data['group_modes'] = $group_modes;

        $placeholders = array(
            '{subTotal}'                => $this->language->get('text_eq_cart_total'),
            '{subTotalWithTax}'         => $this->language->get('text_eq_cart_total_tax'),
            '{weight}'                  => $this->language->get('text_eq_cart_weight'),
            '{quantity}'                => $this->language->get('text_eq_cart_qnty'),
            '{volume}'                  => $this->language->get('text_eq_cart_vol'),
            '{dimensional}'             => $this->language->get('text_eq_dimension'),
            '{volumetric}'              => $this->language->get('text_eq_volumetric'),
            '{subTotalAsPerProductRule}' => $this->language->get('text_eq_method_total'),
            '{subTotalWithTaxAsPerProductRule}' => $this->language->get('text_eq_method_total_tax'),
            '{weightAsPerProductRule}'  => $this->language->get('text_eq_method_weight'),
            '{quantityAsPerProductRule}'=> $this->language->get('text_eq_method_qnty'),
            '{volumeAsPerProductRule}'  => $this->language->get('text_eq_method_vol'),
            '{xfeepro}'                 => $this->language->get('text_eq_xfeepro'),
            '{shippingCost}'            => $this->language->get('text_eq_shipping'),
            '{vouchers}'                => $this->language->get('text_percent_vouchers'),
            '{special}'                 => $this->language->get('text_percent_special'),
            '{modifier}'                => $this->language->get('text_eq_modifier'),
            '{noOfProduct}'             => $this->language->get('text_eq_no_product'),
            '{noOfCategory}'            => $this->language->get('text_eq_no_cat'),
            '{noOfLocation}'            => $this->language->get('text_eq_no_loc'),
            '{noOfManufacturer}'        => $this->language->get('text_eq_no_man'),
            '{noOfLocation}'            => $this->language->get('text_eq_no_loc'),
            '{couponValue}'             => $this->language->get('text_eq_coupon'),
            '{rewardValue}'             => $this->language->get('text_eq_reward'),
            '{grandTotal}'              => $this->language->get('text_grand_total'),
            '{grandWithoutTax}'         => $this->language->get('text_eq_grand_wo_tax'),
            '{distance}'                => $this->language->get('text_rate_type_distance'),
            '{minHeight}, {maxHeight}, {sumHeight}' => $this->language->get('text_eq_height'),
            '{minWidth}, {maxWidth}, {sumWidth}'    => $this->language->get('text_eq_width'),
            '{minLength}, {maxLength}, {sumLength}' => $this->language->get('text_eq_length'),
            '{productWidth}, {productHeight}, {productLength}, {productWeight}, {productQuantity}, {productPrice}, {productVolume}'    => $this->language->get('text_eq_all'),
            '{anyProductWidth}, {anyProductHeight}, {anyProductLength}, {anyProductWeight}, {anyProductQuantity}, {anyProductPrice}, {anyProductSpecialPrice}, {anyProductVolume}' => $this->language->get('text_eq_any')
        );

        $more_help = array();
        $eq_placeholders = $this->getPlaceholderList($placeholders);
        $more_help['equation'] = $eq_placeholders . $this->ocm->misc->getHelpTag($data['more_equation']);
        $more_help['zone'] = $data['more_zone'];
        $more_help['dimensional_factor'] = $data['more_dimensional_factor'];
        $data['more_help'] = json_encode($more_help);
        
        $data['cg_path'] = $cg_path;
        $data['oc_3_1'] = VERSION >= '3.1.0.0';
        $data['global'] = $this->getConfigForm($data);
        $data['tpl'] = json_encode(array(
            'method'     =>  $this->getFormData($data, true)
        ));
        $data['methods'] = $this->getMethodList($data['method_data']);
        $data['form_data'] = $this->getFormData($data);

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $this->response->setOutput($this->ocm->view($this->ext_path, $data));
    }
    private function getConfigForm($data) {
        /* Set base name for form inputs */
        $setting = $this->ocm->setting->getSetting($this->setting, $data['languages']);
        $this->ocm->form->setBasename($this->ocm->prefix . 'xfeepro_', 'prefix');
        $this->ocm->form->setPreset($setting)->setIDPostfix('');

        $return = '';
        $tabs = array(
            'global-general' => $data['tab_global_general'],
            'global-group' => $data['tab_group_option'],
            'global-export' => $data['tab_import_export'],
            'global-help' => $data['tab_help']
        );
        $return .= $this->ocm->misc->getTabs('global-tab', $tabs);
        $return .= '<div class="tab-content">';

        $return .= '<div class="tab-pane active" id="global-general">';
        $return .= $this->ocm->form->get('input', array('name' => 'sort_order', 'help' => $data['help_module_sort_order']));
        $return .= $this->ocm->form->get('select', array('name' => 'status', 'title' => $data['entry_module_status'], 'help' => $data['help_module_status']));
        $return .= $this->ocm->form->get('select', 'debug');
        $return .= '</div>';

        $return .= '<div class="tab-pane" id="global-group">';
        $return .= $this->ocm->misc->getHelpTag($data['help_pro']);
        $return .= '</div>';

        $return .= '<div class="tab-pane" id="global-export">';
        $return .= $this->ocm->misc->getHelpTag($data['help_pro']);
        $return .= '</div>';

        $return .= '<div class="tab-pane" id="global-help">';
        $return .= '<div class="ocm-debug-button"><a class="btn btn-danger" href="javascript:debugBrowser();" role="button">'.$data['text_debug_button'].'</a></div>';
        $return .= $this->ocm->misc->getOCMInfo();
        $return .= '</div>';
        
        $return .= '</div>';
        return $return;
    }
    private function getFormData($data, $new_tab = false) {
        $this->load->model('catalog/category');
        $this->load->model('catalog/product');
        $this->load->model('catalog/option');
        $this->load->model('catalog/manufacturer');
        $this->load->model($data['cg_path'] . '/customer');

        if ($new_tab) {
            $data['method_data'] = array(
                array('tab_id' => '__INDEX__', 'method_data' => array())
            );
        }
        /* Set base name for form inputs */
        $this->ocm->form->setBasename($this->meta['name']);

        $range_types = array('quantity', 'weight', 'volume', 'dimensional', 'volumetric', 'sub', 'total', 'sub_coupon', 'total_coupon', 'grand', 'grand_wtax', 'no_category', 'no_manufacturer', 'no_location', 'distance', 'equation');
        $non_total_types = array('flat','quantity', 'weight', 'volume', 'dimensional', 'volumetric', 'no_category', 'no_manufacturer', 'no_location', 'distance', 'equation');

        $fields_lang = array(
            'name'  => 'Untitled Item'
        );
        $fields_all = array(
            'store'          => 'store_all',
            'geo_zone'       => 'geo_zone_all',
            'zone'           => 'zone_all',
            'country'        => 'country_all', 
            'currency'       => 'currency_all',
            'customer_group' => 'customer_group_all',
            'payment'        => 'payment_all',
            'shipping'       => 'shipping_all',
            'city'           => 'city_all',
            'postal'         => 'postal_all',
            'coupon'         => 'coupon_all',
            'days'           => 'days_all',
            'customers'      => 'customer_all'
        );
        $default_values = $this->getDefaultValues();
        $return = '';
        foreach($data['method_data'] as $single_method) {
            $no_of_tab   = $single_method['tab_id'];
            $method_data = $single_method['method_data'];
            $method_data = $this->resetEmptyAll($method_data, $fields_all);
            $method_data = array_merge($default_values, $method_data);
            $method_data = $this->setDefaultByLangs($method_data, $data['languages'], $fields_lang);
            if (!$method_data['display']) {
                $method_data['display'] = $this->getLangField($method_data, 'name');
            }

            $this->ocm->form->setPreset($method_data)->setIDPostfix($no_of_tab);

            $return .= '<div id="ocm-method-'.$no_of_tab.'" class="tab-pane xfeepro ocm-method">';
            $return .= '<div class="ocm-action-btn">';
            $return .= $this->ocm->misc->getButton(array('type' => 'warning', 'help'=> $data['text_method_copy'], 'class' => 'btn-ocm-copy btn-sm', 'icon' => 'fa-copy'));
            $return .= $this->ocm->misc->getButton(array('type' => 'danger', 'help'=> $data['text_method_remove'], 'class' => 'btn-ocm-delete btn-sm', 'icon' => 'fa-trash fa-trash-alt'));
            $return .= '</div>';

            $return .= $this->ocm->form->get('input', 'display');

            $return .= $this->ocm->misc->getLangTabs('language_' . $no_of_tab, $data['languages']);

            $active = ' active';
            $return .= '<div class="tab-content">';
            foreach ($data['languages'] as $language) { 
                $language_id = $language['language_id'];
                $return .= '<div class="tab-pane' . $active . '" id="language_' . $no_of_tab . '-' . $language_id . '">';

                $param = array(
                    'name'  => 'name[' . $language_id . ']',
                    'required' => true
                );
                $return .= $this->ocm->form->get('input', $param);
                $return .= '</div>';
                $active = '';
            }
            $return .= '</div>';

            $tabs = array(
                'common_' . $no_of_tab      => $data['tab_general'],
                'criteria_' . $no_of_tab    => $data['tab_criteria_setting'],
                'catprod_' . $no_of_tab     => $data['tab_category_product'],
                'price_' . $no_of_tab       => $data['tab_price_setting'],
                'event_' . $no_of_tab => $data['tab_condition_event']
            );
            $return .= $this->ocm->misc->getTabs('method-tab' . $no_of_tab, $tabs);

            $return .= '<div class="tab-content">';
            $return .= '<div class="tab-pane active" id="common_' . $no_of_tab . '">';
            $return .= $this->ocm->form->get('select', 'tax_class_id');
            $return .= $this->ocm->form->get('input', 'sort_order');
            $return .= $this->ocm->form->get('select', 'status');
            $return .= '</div>';
            $return .= '<div class="tab-pane" id="criteria_'.$no_of_tab.'">';
            $return .= $this->ocm->form->get('checkgroup', array('name' => 'geo_zone[]', 'search' => true));
            $return .= $this->ocm->form->get('checkgroup', 'payment[]');
            $return .= $this->ocm->form->get('checkgroup', 'shipping[]');
            $return .= $this->ocm->misc->getHelpTag($data['help_pro_plus']);
            $return .= '</div>';
            $return .= '<div class="tab-pane" id="catprod_'.$no_of_tab.'">';
            $return .= $this->ocm->misc->getHelpTag($data['help_pro_plus']);
            $return .= '</div>';

            $return .= '<div class="tab-pane" id="price_'.$no_of_tab.'">';

            $return .= $this->ocm->form->get('select', 'rate_type');
            $return .= $this->ocm->form->get('input', array('name' => 'cost', 'class' => 'rate_type flat'));
            $return .= $this->ocm->misc->getHelpTag($data['help_pro_plus']);
            $return .= '</div>';

            $return .= '<div class="tab-pane" id="event_'.$no_of_tab.'">';
            $return .= $this->ocm->misc->getHelpTag($data['help_pro_plus']);
            $return .= '</div>';
            /* End of event tab */
            $return .= '</div>';
            $return .= '</div>';
        }
        
        return $return;
    }
    private function getDefaultValues() {
        return array(
            /* array rules */   
            'customer_group'    => array(),
            'geo_zone'          => array(),
            'product_category'  => array(),
            'product_product'   => array(),
            'store'             => array(),
            'currency'          => array(),
            'payment'           => array(),
            'shipping'          => array(),
            'manufacturer'      => array(),
            'days'              => array(),
            'ranges'            => array(),
            'products'          => array(),
            'country'           => array(),
            'zone'              => array(),
            'name'              => array(),
            'product_option'    => array(),
            'hide'              => array(),
            'hide_inactive'     => array(),
            'location'          => array(),
            'customers'         => array(),
            /* string/numberic rules*/
            'dimensional_factor'    => '',
            'dimensional_overfule'  => '',
            'customer_group_all'    => '',
            'geo_zone_all'          => '',
            'country_all'           => '',
            'zone_all'              => '',
            'store_all'             => '',
            'manufacturer_all'      => '',
            'postal_all'            => '',
            'coupon_all'            => '',
            'currency_all'          => '',
            'payment_all'           => '',
            'shipping_all'          => '',
            'city_all'              => '',
            'days_all'              => '',
            'customer'              => '',
            'city'                  => '',
            'postal'                => '',
            'coupon'                => '',
            'city_rule'             => 'inclusive',
            'postal_rule'           => 'inclusive',
            'coupon_rule'           => 'inclusive',
            'time_start'            => '',
            'time_end'              => '',
            'rate_final'            => 'single',
            'rate_percent'          => 'sub',
            'rate_min'              => '',
            'rate_max'              => '',
            'rate_add'              => '',
            'location_rule'         => '',
            'manufacturer_rule'     => '',
            'additional'            => '',
            'additional_per'        => '',
            'additional_limit'      => '',
            'group'                 => 'no_group',
            'order_total_start'     => '',
            'order_total_end'       => '',
            'weight_start'          => '',
            'weight_end'            => '',
            'quantity_start'        => '',
            'quantity_end'          => '',
            'equation'              => '',
            'tax_class_id'          => '',
            'option'                => '',
            'sort_order'            => '',
            'status'                => 1,
            'category'              => '',
            'product'               => '',
            'rate_type'             => 'flat',
            'cost'                  => '',
            'display'               => 'Untitled Item',
            'ingore_product_rule'   => '',
            'product_or'            => '',
            'method_specific'       => '',
            'date_start'            => '',
            'date_end'              => '',
            'inc_vat'               => '',
            'fake'                  => '',
            'cart_adjust'           => 0
        );
    }
    private function getRanges($method_data, $data, $no_of_tab) {
        $fields = array('start', 'end', 'cost', 'block', 'partial');
        $return = '';
        $return .='<div class="ocm-range-container">
                    <div class="ocm-range-option">';
        $return .= '   <div class="price-range">'.$data['entry_unit_range'].'</div>';
        $return .= '   <a href="'.$data['export'].'&no='.$no_of_tab.'" class="btn btn-info export-btnbtn-sm range-btn" role="button">'.$data['text_export'].'</a>';
        $return .=     $this->ocm->misc->getButton(array('type' => 'danger', 'title'=> $data['text_delete_all'], 'class' => 'ocm-row-remove-all btn-sm range-btn'));
        $return .=     $this->ocm->misc->getButton(array('type' => 'primary', 'title'=> $data['text_csv_import'], 'class' => 'range-import-btn btn-sm range-btn'));
        $return .= '</div>';

        $ranges = array();
        foreach ($method_data['ranges'] as $counter => $range) {
            foreach ($fields as $field) {
                if (!isset($range[$field])) {
                    $range[$field] = '';
                }
            }
            $ranges[] = $range;
        }

        $table_body = '';
        foreach ($ranges as $counter => $range) {
            $table_body .= '<tr rel="'.$counter.'">' 
                            .'<td class="text-left"><input size="15" type="text" class="form-control" name="xfeepro[ranges]['.$counter.'][start]" value="' . $range['start'] . '" /></td>'
                            .'<td class="text-left"><input size="15" type="text" class="form-control" name="xfeepro[ranges]['.$counter.'][end]" value="' . $range['end'] . '" /></td>'
                            .'<td class="text-left"><input size="15" type="text" class="form-control" name="xfeepro[ranges]['.$counter.'][cost]" value="' . $range['cost'] . '" /></td>'
                            .'<td class="text-left"><input size="6" type="text" class="form-control" name="xfeepro[ranges]['.$counter.'][block]" value="' . $range['block'] . '" /></td>'
                            .'<td class="text-left">
                                <select name="xfeepro[ranges]['.$counter.'][partial]">
                                    <option '.(($range['partial']=='0') ? 'selected': '' ) . ' value="0">' . $data['text_no'] . '</option>
                                    <option '.(($range['partial']=='1') ? 'selected': '' ) .' value="1">' . $data['text_yes'] . '</option>
                                </select>
                            </td>'
                            .'<td class="text-right"><a class="btn btn-sm btn-danger ocm-row-remove">'.$data['text_remove'].'</a></td>'
                        .'</tr>';
        }
        if (!$method_data['ranges']) $table_body .= '<tr class="no-row"><td colspan="6">'.$data['text_no_unit_row'].'</td></tr>';

        $table_headings = array(
            array(
                'title'  => $data['text_start'],
                'help'   => $data['help_unit_start']
            ),
            array(
                'title'  => $data['text_end'],
                'help'   => $data['help_unit_end']
            ),
            array(
                'title' => $data['text_cost'],
                'help'  => $data['help_unit_price']
            ),
            array(
                'title' => $data['text_qnty_block'],
                'help'  => $data['help_unit_ppu']
            ),
            array(
                'title' => $data['text_partial'],
                'help'  => $data['help_partial']
            ),
            array(
                'title' => $data['text_action']
            )
        );
        $table_footer = '<tfoot>
                           <td colspan="7" class="text-right">&nbsp;';
        $table_footer .= $this->ocm->misc->getButton(array('type' => 'primary', 'title'=> $data['text_add_new'], 'class' => 'add-ocm-row', 'icon' => 'fa-plus-circle'));
        $table_footer .= '</tr>
                        </tfoot>';
        $return .= $this->ocm->misc->getTableSkeleton($table_headings, $table_body, $table_footer);
        $return .=  '</div>';
        return $return;
    }
    // validate hook for sort_order value
    private function onValidateGeneral(&$save) {
        $key = VERSION >= '3.0.0.0' ? 'total_' . $this->meta['name'] . '_sort_order' : $this->meta['name'] . '_sort_order';
        $min_value = (int)$this->ocm->common->getConfig('sub_total_sort_order', 'total') + 1;
        $max_value = (int)$this->ocm->common->getConfig('total_sort_order', 'total') - 1;
        if ((int)$save['value'][$key] < (int)$min_value) {
            $save['value'][$key] = $min_value;
        }
        if ((int)$save['value'][$key] > (int)$max_value) {
            $save['value'][$key] = $max_value;
        }
    }
 }