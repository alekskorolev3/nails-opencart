<?php
class ControllerExtensionShippingXshippingpro extends Controller {
    use OCM\Traits\Back\Controller\Common;
    use OCM\Traits\Back\Controller\Crud;
    use OCM\Traits\Back\Controller\Product;
    use OCM\Traits\Back\Controller\Util;
    private $ext_path;
    private $ext_key;
    private $error = array();
    private $ocm;
    private $meta = array(
        'id'       => '13705',
        'type'     => 'shipping',
        'name'     => 'xshippingpro',
        'path'     => 'extension/shipping/',
        'title'    => 'X-Shippingpro',
        'version'  => '3.3.9',
        'ocmod'    => false
    );
    /* Config with default values  Special keyword __LANG__ denotes array of languages e.g 'name' => array('__LANG__' => 'xyz') */
    private $setting = array(
        'xshippingpro_status' => '',
        'xshippingpro_debug' => '',
        'xshippingpro_sort_order' => '',
        'xshippingpro_sorting'   => '',
        'xshippingpro_heading'   => array('__LANG__' => 'Shipping Options'),
    );
    private $events = array();
    private $tables = array(
        'xshippingpro' => array (
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
        //$ext_lang['more_equation'] = true; // will replace by placeholder 

        $data = array();
        $data = array_merge($data, $ext_lang);
        $data['_v'] = '';
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
        $data['export'] = $this->ocm->url->link('extension/shipping/xshippingpro/export', '', true);

        $this->load->model('localisation/language');
        $languages = $this->model_localisation_language->getLanguages();
        $data['languages'] = $this->ocm->url->getLangImage($languages);
        $data['language_id'] = $this->config->get('config_language_id');

        $data['sub_groups_count'] = 10;

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
        $options['store'] = $options['estimator_store'] = $this->ocm->form->getOptions($stores, 'store_id');

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
            'flat'            => $data['text_rate_flat'],
            'sub'             => $data['text_rate_sub_total'],
            'total'           => $data['text_rate_total']
        );
        $options['rate_type'] = $this->ocm->form->getOptions($rate_type_options, 'none');

        $percent_options = array(
            'sub'             => $data['text_percent_sub_total'],
            'total'           => $data['text_percent_total'],
            'sub_shipping'    => $data['text_percent_sub_total_shipping'],
            'total_shipping'  => $data['text_percent_total_shipping'],
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

        $sorting = array(
            '1' => $data['text_sort_manual'],
            '2' => $data['text_sort_price_asc'],
            '3' => $data['text_sort_price_desc'],
            '4' => $data['text_sort_name_asc'],
            '5' => $data['text_sort_name_desc']
        );
        $options['sorting'] = $this->ocm->form->getOptions($sorting, 'none');

        $estimator_fields = array(
            'country' => $data['text_estimator_country'],
            'zone' => $data['text_estimator_zone'],
            'postal' => $data['text_estimator_postal']
        );
        $options['estimator_fields'] = $this->ocm->form->getOptions($estimator_fields, 'none');

        $estimator_type = array(
            'method' => $data['text_type_avail'],
            'avail' => $data['text_type_method']
        );
        $options['estimator_type'] = $this->ocm->form->getOptions($estimator_type, 'none');

        $group = array();
        $group[] = array(
            'name' => 'None',
            'value' => 0
        );
        for ($i=1; $i <= $data['sub_groups_count']; $i++) {
            $group[] = array(
                'name' => 'Group' . $i,
                'value' => $i
            );
        }
        $options['group'] = $group;

        $group_limit = array('1' => 1, '2' => 2, '3' => 3, '4' => 4, '5' => 5);
        $options['group_limit'] = $this->ocm->form->getOptions($group_limit, 'none');
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
            '{subTotal}' => $this->language->get('text_eq_cart_total'),
            '{subTotalWithTax}' => $this->language->get('text_eq_cart_total_tax'),
            '{weight}' => $this->language->get('text_eq_cart_weight'),
            '{quantity}' => $this->language->get('text_eq_cart_qnty'),
            '{volume}' => $this->language->get('text_eq_cart_vol'),
            '{subTotalAsPerProductRule}' => $this->language->get('text_eq_method_total'),
            '{subTotalWithTaxAsPerProductRule}' => $this->language->get('text_eq_method_total_tax'),
            '{weightAsPerProductRule}' => $this->language->get('text_eq_method_weight'),
            '{quantityAsPerProductRule}' => $this->language->get('text_eq_method_qnty'),
            '{volumeAsPerProductRule}' => $this->language->get('text_eq_method_vol'),
            '{shipping}' => $this->language->get('text_eq_shipping'),
            '{modifier}' => $this->language->get('text_eq_modifier'),
            '{noOfCategory}' => $this->language->get('text_eq_no_cat'),
            '{noOfLocation}' => $this->language->get('text_eq_no_loc'),
            '{noOfManufacturer}' => $this->language->get('text_eq_no_man'),
            '{noOfLocation}' => $this->language->get('text_eq_no_loc'),
            '{couponValue}' => $this->language->get('text_eq_coupon'),
            '{rewardValue}' => $this->language->get('text_eq_reward'),
            '{grandTotal}' => $this->language->get('text_grand_total'),
            '{grandBeforeShipping}' => $this->language->get('text_grand_total_before_shiping'),
            '{distance}' => $this->language->get('text_rate_type_distance'),
            '{anyProductWidth}, {anyProductHeight}, {anyProductLength}, {anyProductWeight}, {anyProductQuantity}' => $this->language->get('text_eq_any'),
            '{minHeight}, {maxHeight}, {sumHeight}' => $this->language->get('text_eq_height'),
            '{minWidth}, {maxWidth}, {sumWidth}' => $this->language->get('text_eq_width'),
            '{minLength}, {maxLength}, {sumLength}' => $this->language->get('text_eq_length')
        );

        $more_help = array();
        $more_help['zone'] = $data['more_zone'];
        $more_help['error'] = $data['more_error'];
        $more_help['dimensional_factor'] = $data['more_dimensional_factor'];
        $data['more_help'] = json_encode($more_help);
        
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
        $this->ocm->form->setBasename($this->ocm->prefix . 'xshippingpro_', 'prefix');
        $this->ocm->form->setPreset($setting)->setIDPostfix('');

        $return = '';
        $return .= $this->ocm->misc->getLangTabs('language_heading', $data['languages']);
        $active = ' active';
        $return .= '<div class="tab-content">';
        foreach ($data['languages'] as $language) { 
            $language_id = $language['language_id'];
            $return .= '<div class="tab-pane' . $active . '" id="language_heading' . '-' . $language_id . '">';

            $param = array(
                'name'  => 'heading[' . $language_id . ']',
                'required' => true
            );
            $return .= $this->ocm->form->get('input', $param);

            $return .= '</div>';
            $active = '';
        }
        $return .= '</div>';

        $tabs = array(
            'global-general' => $data['tab_global_general'],
            'global-group' => $data['tab_group_option'],
            'global-export' => $data['tab_import_export'],
            'global-estimator' => $data['tab_estimator'],
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

        $return .= '<div class="tab-pane" id="global-estimator">';
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
        $cg_path = (VERSION >= '2.1.0.1') ? 'customer' : 'sale';
        $this->load->model($cg_path . '/customer');

        if ($new_tab) {
            $data['method_data'] = array(
                array('tab_id' => '__INDEX__', 'method_data' => array())
            );
        }

        /* Set base name for form inputs */
        $this->ocm->form->setBasename($this->meta['name']);
        $range_types = array('quantity', 'weight', 'volume', 'dimensional', 'volumetric', 'sub', 'total', 'sub_coupon', 'total_coupon', 'grand_shipping', 'grand', 'no_category', 'no_manufacturer', 'no_location', 'distance', 'equation');
        $non_total_types = array('flat','quantity', 'weight', 'volume', 'dimensional', 'volumetric', 'no_category', 'no_manufacturer', 'no_location', 'distance', 'equation');

        $fields_lang = array(
            'name'  => 'Untitled Item'
        );
        $fields_all = array(
            'store' => 'store_all',
            'geo_zone' => 'geo_zone_all',
            'zone'  => 'zone_all',
            'country' => 'country_all', 
            'currency' => 'currency_all',
            'customer_group' => 'customer_group_all',
            'payment' => 'payment_all',
            'city' => 'city_all',
            'postal' => 'postal_all',
            'coupon' => 'coupon_all',
            'days'   => 'days_all',
            'customers'   => 'customer'
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

            $return .= '<div id="ocm-method-'.$no_of_tab.'" class="tab-pane xshippingpro ocm-method">';
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
                'sub-options_' . $no_of_tab => $data['tab_sub_options'],
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
            $return .= $this->ocm->form->get('checkgroup', 'store[]');
            $return .= $this->ocm->form->get('checkgroup', array('name' => 'geo_zone[]', 'search' => true));
            $return .= $this->ocm->misc->getHelpTag($data['help_pro_plus']);
            $return .= '</div>';
            $return .= '<div class="tab-pane" id="catprod_'.$no_of_tab.'">';
            $return .= $this->ocm->misc->getHelpTag($data['help_pro']);
            $return .= '</div>';

            $return .= '<div class="tab-pane" id="price_'.$no_of_tab.'">';

            $return .= $this->ocm->form->get('select', 'rate_type');
            $return .= $this->ocm->form->get('input', array('name' => 'cost', 'visible' => $method_data['rate_type']=='flat', 'class' => 'rate_type ocm-hide flat'));

            $element = $this->getRanges($method_data, $data, $no_of_tab);
            $return .= $this->ocm->form->get('bare', array('name' => 'unit_range', 'label_col' => 0, 'element' => $element, 'visible' => $method_data['rate_type'] != 'flat', 'class' => 'rate_type ocm-hide product ' . implode(' ', $range_types)));
            $return .= $this->ocm->misc->getHelpTag($data['help_pro_plus']);
            $return .= '</div>';

            $return .= '<div class="tab-pane" id="event_'.$no_of_tab.'">';
            $return .= $this->ocm->misc->getHelpTag($data['help_pro']);
            $return .= '</div>';

            $return .= '<div class="tab-pane" id="sub-options_' . $no_of_tab . '">';
            $return .= $this->ocm->misc->getHelpTag($data['help_pro']);
            $return .= '</div>';
            /* End of Sub-option tab */
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
            'manufacturer'      => array(),
            'days'              => array(),
            'ranges'            => array(),
            'products'          => array(),
            'country'           => array(),
            'zone'              => array(),
            'name'              => array(),
            'desc'              => array(),
            'product_option'    => array(),
            'hide'              => array(),
            'hide_inactive'     => array(),
            'location'          => array(),
            'sub_options'       => array(),
            'customers'         => array(),

            /* string/numberic rules*/
            'inc_weight'            => '',
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
            'disable'               => '',
            'additional'            => '',
            'additional_per'        => '',
            'additional_limit'      => '',
            'other_product'         => '',
            'other_product_per'     => '',
            'logo'                  => '',
            'group'                 => 'no_group',
            'order_total_start'     => '',
            'order_total_end'       => '',
            'weight_start'          => '',
            'weight_end'            => '',
            'quantity_start'        => '',
            'quantity_end'          => '',
            'mask'                  => '',
            'equation'              => '',
            'tax_class_id'          => '',
            'option'                => 1,
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
            'free_option'           => '',
            'date_start'            => '',
            'date_end'              => '',
            'equation_neg'          => '',
            'exc_vat'               => '',
            'max_height'            => '',
            'max_width'             => '',
            'max_length'            => '',
            'cart_adjust'           => 0
        );
    }
    private function getRanges($method_data, $data, $no_of_tab) {
        $fields = array('start', 'end', 'product_id', 'cost', 'block', 'partial', 'type');
        $class = $method_data['rate_type'] == 'product' ? ' product-cols' : ' range-cols';
        $return = '';
        $return .='<div class="ocm-range-container'.$class.'">
                    <div class="ocm-range-option">';
        $return .= '   <div class="price-range">'.$data['entry_unit_range'].'</div>';
        $return .=     $this->ocm->misc->getButton(array('type' => 'danger', 'title'=> $data['text_delete_all'], 'class' => 'ocm-row-remove-all btn-sm range-btn'));
        $return .= '</div>';

        $ranges = array();
        $product_ids = array();
        foreach ($method_data['ranges'] as $counter => $range) {
            foreach ($fields as $field) {
                if (!isset($range[$field])) {
                    $range[$field] = '';
                }
            }
            $ranges[] = $range;
            if ($range['product_id']) {
                $product_ids[] = $range['product_id'];
            }
        }

        $products = $this->{$this->ext_key}->getBatchProducts($product_ids);
        $table_body = '';
        foreach ($ranges as $counter => $range) {
            $name = '';
            if ($method_data['rate_type'] == 'product') {
                if (!isset($products[$range['product_id']])) {
                    continue;
                }
                $name = $products[$range['product_id']]['name'];
            }
            
            $table_body .= '<tr rel="'.$counter.'">' 
                            .'<td class="text-left ocm-hide product">'.$name.'<input type="hidden" name="xshippingpro[ranges]['.$counter.'][product_id]" value="' . $range['product_id'] . '" /></td>'
                            .'<td class="text-left ocm-hide range"><input size="15" type="text" class="form-control" name="xshippingpro[ranges]['.$counter.'][start]" value="' . $range['start'] . '" /></td>'
                            .'<td class="text-left ocm-hide range"><input size="15" type="text" class="form-control" name="xshippingpro[ranges]['.$counter.'][end]" value="' . $range['end'] . '" /></td>'
                            .'<td class="text-left"><input size="15" type="text" class="form-control" name="xshippingpro[ranges]['.$counter.'][cost]" value="' . $range['cost'] . '" /></td>'
                            .'<td class="text-left ocm-hide"><input size="6" type="text" class="form-control" name="xshippingpro[ranges]['.$counter.'][block]" value="' . $range['block'] . '" /></td>'
                            .'<td class="text-left ocm-hide">
                                <select name="xshippingpro[ranges]['.$counter.'][partial]">
                                    <option '.(($range['partial']=='0') ? 'selected': '' ) . ' value="0">' . $data['text_no'] . '</option>
                                    <option '.(($range['partial']=='1') ? 'selected': '' ) .' value="1">' . $data['text_yes'] . '</option>
                                </select>
                            </td>'
                           .'<td class="text-left ocm-hide product">
                                <select name="xshippingpro[ranges]['.$counter.'][type]">
                                    <option '.(($range['type']=='quantity') ? 'selected': '' ) . ' value="quantity">' . $data['text_rate_quantity'] . '</option>
                                    <option '.(($range['type']=='weight') ? 'selected': '' ) .' value="weight">' . $data['text_rate_weight'] . '</option>
                                    <option '.(($range['type']=='price') ? 'selected': '' ) .' value="price">' . $data['text_product_price'] . '</option>
                                </select>
                            </td>'
                            .'<td class="text-right"><a class="btn btn-sm btn-danger ocm-row-remove">'.$data['text_remove'].'</a></td>'
                        .'</tr>';
        }
        if (!$method_data['ranges']) $table_body .= '<tr class="no-row"><td colspan="6">'.$data['text_no_unit_row'].'</td></tr>';

        $table_headings = array(
            array(
                'title'  => $data['text_product_name'],
                'class'  => 'ocm-hide product'
            ),
            array(
                'title'  => $data['text_start'],
                'help'   => $data['help_unit_start'],
                'class'  => 'ocm-hide range'
            ),
            array(
                'title'  => $data['text_end'],
                'help'   => $data['help_unit_end'],
                'class'  => 'ocm-hide range'
            ),
            array(
                'title' => $data['text_cost'],
                'help'  => $data['help_unit_price']
            ),
            array(
                'title' => $data['text_qnty_block'],
                'help'  => $data['help_unit_ppu'],
                'class'  => 'ocm-hide'
            ),
            array(
                'title' => $data['text_partial'],
                'help'  => $data['help_partial'],
                'class'  => 'ocm-hide'
            ),
            array(
                'title' => $data['text_product_unit_type'],
                'help'  => $data['help_product_unit_type'],
                'class'  => 'ocm-hide product'
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
 }