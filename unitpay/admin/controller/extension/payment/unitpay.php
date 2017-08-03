<?php
class ControllerExtensionPaymentUnitpay extends Controller {

	private $error = array();

	public function index() {
		$this->load->language('extension/payment/unitpay');

		$this->document->setTitle = $this->language->get('heading_title');

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && ($this->validate())) {
			$this->model_setting_setting->editSetting('payment_unitpay', $this->request->post);
			$this->session->data['success'] = $this->language->get('text_success');

            $this->response->redirect($this->url->link('extension/payment/unitpay', 'user_token=' . $this->session->data['user_token'], true));
		}

		$data['heading_title'] = $this->language->get('heading_title');

		$data['text_enabled'] = $this->language->get('text_enabled');
		$data['text_disabled'] = $this->language->get('text_disabled');
		$data['text_all_zones'] = $this->language->get('text_all_zones');
		$data['text_liqpay'] = $this->language->get('text_liqpay');
		$data['text_card'] = $this->language->get('text_card');
		$data['text_yes'] = $this->language->get('text_yes');
		$data['text_no'] = $this->language->get('text_no');
		$data['text_edit'] = $this->language->get('text_edit');
		$data['text_result_url'] = $this->language->get('text_result_url');
		$data['text_success_url'] = $this->language->get('text_success_url');
		$data['text_fail_url'] = $this->language->get('text_fail_url');
		$data['text_save_and_stay'] = $this->language->get('text_save_and_stay');


		$data['entry_login'] = $this->language->get('entry_login');
		$data['entry_unitpay_key'] = $this->language->get('entry_unitpay_key');

		// URL
		$data['copy_result_url'] 	= HTTP_CATALOG . 'index.php?route=extension/payment/unitpay/callback';
		$data['copy_success_url']	= HTTP_CATALOG . 'index.php?route=checkout/success';
		$data['copy_fail_url'] 	= HTTP_CATALOG . 'index.php?route=checkout/failure';

		$data['entry_order_status_after_pay'] = $this->language->get('entry_order_status_after_pay');
		$data['entry_order_status_after_create'] = $this->language->get('entry_order_status_after_create');
		$data['entry_order_status_error'] = $this->language->get('entry_order_status_error');
		$data['entry_delete_cart_after_confirm'] = $this->language->get('entry_delete_cart_after_confirm');
		$data['entry_set_status_after_create'] = $this->language->get('entry_set_status_after_create');
		$data['entry_set_status_after_error_payment']     = $this->language->get('entry_set_status_after_error_payment');
		$data['entry_geo_zone'] = $this->language->get('entry_geo_zone');
		$data['entry_status'] = $this->language->get('entry_status');
		$data['entry_sort_order'] = $this->language->get('entry_sort_order');

		$data['button_save'] = $this->language->get('button_save');
		$data['button_cancel'] = $this->language->get('button_cancel');

		$data['tab_general'] = $this->language->get('tab_general');

		//

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		//
		if (isset($this->error['login'])) {
			$data['error_login'] = $this->error['login'];
		} else {
			$data['error_login'] = '';
		}

		if (isset($this->error['password1'])) {
			$data['error_password1'] = $this->error['password1'];
		} else {
			$data['error_password1'] = '';
		}

		$data = array_merge($data, array(
		    'payment_unitpay_login' => $this->getRequestParam('payment_unitpay_login'),
            'payment_unitpay_key' => $this->getRequestParam('payment_unitpay_key'),
            'payment_unitpay_order_status_id_after_create' => $this->getRequestParam('payment_unitpay_order_status_id_after_create'),
            'payment_unitpay_order_status_id_error' => $this->getRequestParam('payment_unitpay_order_status_id_error'),
            'payment_unitpay_order_status_id_after_pay' => $this->getRequestParam('payment_unitpay_order_status_id_after_pay'),
            'payment_unitpay_create_order' => $this->getRequestParam('payment_unitpay_create_order'),
            'payment_unitpay_cart_reset' => $this->getRequestParam('payment_unitpay_cart_reset'),
            'payment_unitpay_set_error_status' => $this->getRequestParam('payment_unitpay_set_error_status'),
            'payment_unitpay_geo_zone_id' => $this->getRequestParam('payment_unitpay_geo_zone_id'),
            'payment_unitpay_status' => $this->getRequestParam('payment_unitpay_status'),
            'payment_unitpay_min_price' => $this->getRequestParam('payment_unitpay_min_price'),
            'breadcrumbs' => array(
                array(
                    'text'      => $this->language->get('text_home'),
                    'href'      => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true),
                    'separator' => false
                ),
                array(
                    'text'      => $this->language->get('text_payment'),
                    'href'      => $this->url->link('extension/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true),
                    'separator' => ' :: '
                ),
                array(
                    'text'      => $this->language->get('heading_title'),
                    'href'      => $this->url->link('extension/payment/unitpay', 'user_token=' . $this->session->data['user_token'], true),
                    'separator' => ' :: '
                )
            ),
            'action' => $this->url->link('extension/payment/unitpay', 'user_token=' . $this->session->data['user_token'], true),
            'update' => $this->url->link('extension/payment/unitpay', 'user_token=' . $this->session->data['user_token'], true),
            'cancel' => $this->url->link('extension/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true),
        ));

		$this->load->model('localisation/order_status');
		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		$this->load->model('localisation/geo_zone');
		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/payment/unitpay', $data));

	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/payment/unitpay')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if (!$this->request->post['payment_unitpay_login']) {
			$this->error['login'] = $this->language->get('error_login');
		}

		if (!$this->request->post['payment_unitpay_key']) {
			$this->error['password1'] = $this->language->get('error_password1');
		}

		return !$this->error;
	}

	private function getRequestParam($name)
    {
        if (isset($this->request->post[$name])) {
            return $this->request->post[$name];
        } else {
            return $this->config->get($name);
        }
    }
}
?>