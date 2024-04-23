<?php
class ControllerExtensionShippingXshippingpro extends Controller {
    private $ext_path;
    private $mtype;
    public function __construct($registry) {
        parent::__construct($registry);
        $this->registry = $registry;
        $this->ocm = ($ocm = $this->registry->get('ocm_front')) ? $ocm : new OCM\Front($this->registry);
        $this->mtype = 'shipping';
        $this->ext_path = 'extension/shipping/xshippingpro';
    }
    public function onOrderEmail($route, &$data) {
        $xshippingpro_desc_mail = $this->ocm->getConfig('xshippingpro_desc_mail', $this->mtype);
        if ($xshippingpro_desc_mail) {
            $order_info = $this->model_checkout_order->getOrder($data['order_id']);
            $language_id = $order_info['language_id'];
            if (strpos($order_info['shipping_code'], 'xshippingpro') !== false) {
                $this->load->model($this->ext_path);
                $tab_id = str_replace('xshippingpro.xshippingpro', '', $order_info['shipping_code']);
                $desc_logo =  $this->model_extension_shipping_xshippingpro->getShippingDesc();
                if ($desc_logo && $desc_logo['desc'] && isset($desc_logo['desc'][$tab_id])) {
                    $data['shipping_method'] .= '<br /><span style="color: #999999;font-size: 11px;display:block" class="x-shipping-desc">' . $desc_logo['desc'][$tab_id] . '</span>';
                }
            }
        }
    }
    
    public function estimate_shipping() {
        $json=array();
        $this->load->model($this->ext_path);
        $this->load->language($this->ext_path);
        
        $xshippingpro_estimator =  $this->ocm->getConfig('xshippingpro_estimator', $this->mtype);
        $estimator_type = (isset($xshippingpro_estimator['type']) && $xshippingpro_estimator['type']) ? $xshippingpro_estimator['type'] : 'method';
        $address = array();
        if ($estimator_type == 'avail') {
            $address = array('only_address_rule' => true);
        }
        
        $json =  $this->model_extension_shipping_xshippingpro->getQuote($address);
        if ($estimator_type == 'avail') {
            if ($json) {
                $json = array();
                $json['message'] = $this->language->get('xshippingpro_available');
                $json['class'] = 'avail';
            } else {
                $json = array();
                $json['message'] = $this->language->get('xshippingpro_no_available');
                $json['class'] = 'no_avail';
            }
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}
