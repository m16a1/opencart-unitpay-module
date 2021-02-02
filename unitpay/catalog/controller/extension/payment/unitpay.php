<?php

class ControllerExtensionPaymentUnitpay extends Controller
{
    public function index()
    {
        $data['button_confirm'] = $this->language->get('button_confirm');
        $data['button_back'] = $this->language->get('button_back');
        $data['text_loading'] = $this->language->get('text_loading');

        $this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

        $data['payment_unitpay_domain'] = $this->config->get('payment_unitpay_domain');
        $data['payment_unitpay_login'] = $this->config->get('payment_unitpay_login');
        $data['payment_unitpay_key'] = $this->config->get('payment_unitpay_key');
		$data['payment_unitpay_nds'] = $this->config->get('payment_unitpay_nds');
		$data['payment_unitpay_delivery_nds'] = $this->config->get('payment_unitpay_delivery_nds');
		
        $data['success_url'] = $this->config->get('unitpay_success_url');
        // Номер заказа
        $data['inv_id'] = $this->session->data['order_id'];

        // Комментарий к заказу
        $data['inv_desc'] = "Оплата заказа № " . $data['inv_id'];

        // Сумма заказа
        $rur_code = 'RUB';
        $rur_order_total = $this->currency->convert($order_info['total'], $order_info['currency_code'], $rur_code);
        $data['out_summ'] = $this->currency->format($rur_order_total, $rur_code, $order_info['currency_value'], FALSE);
        $data['out_summ'] = number_format($data['out_summ'], 2, '.', '');

        // Общая сумма в выбранной валюте
        $totalAmount = number_format(($order_info['total'] * $order_info['currency_value']), 2, '.', '');

        $data['action'] = "https://{$data['payment_unitpay_domain']}/pay/";

        $data['merchant_url'] = $data['action'] .
            $data['payment_unitpay_login'] . '?' . http_build_query(array(
                'sum' => $totalAmount,
                'currency' => $order_info['currency_code'],
                'account' => $data['inv_id'],
                'desc' => $data['inv_desc'],
                'cashItems' => $this->getOrderItems($order_info['currency_code'], $order_info['currency_value'], $data),
                'customerEmail' => $order_info['email'],
                'customerPhone' => preg_replace('/\D/', '', $order_info['telephone']),
                'signature' => hash('sha256', join('{up}', array(
                    $data['inv_id'],
                    $order_info['currency_code'],
                    $data['inv_desc'],
                    $totalAmount,
                    $data['payment_unitpay_key']
                )))
            ));

        $this->id = 'payment';

        return $this->load->view('extension/payment/unitpay', $data);
    }

    public function callback()
    {
        echo $this->getResult();
    }

    public function getResult()
    {
        $request = $_GET;

        if (empty($request['method']) || empty($request['params']) || !is_array($request['params'])) {
            return $this->getResponseError('Invalid request');
        }

        $method = $request['method'];
        $params = $request['params'];
        $this->load->model('checkout/order');
        $arOrder = $this->model_checkout_order->getOrder($params['account']);

        // Сумма заказа
        $rur_code = 'RUB';
        $rur_order_total = $this->currency->convert($arOrder['total'], $arOrder['currency_code'], $rur_code);
        $total_price = $this->currency->format($rur_order_total, $rur_code, $arOrder['currency_value'], FALSE);
        $total_price = number_format($total_price, 2, '.', '');

        // Общая сумма в выбранной валюте
		$totalAmount = number_format(($arOrder['total'] * $arOrder['currency_value']), 2, '.', '');

        if ($params['signature'] != $this->getSha256SignatureByMethodAndParams(
                $method, $params, $this->config->get('payment_unitpay_key'))
        ) {
            return $this->getResponseError('Incorrect digital signature');
        }


        if ($method == 'check') {

            if (!$arOrder) {
                return $this->getResponseError('Can\'t find order');
            }

            if (number_format($params['orderSum'], 2, '.', '') != $totalAmount) {
                return $this->getResponseError('Сумма оплаты в ' . $params['orderSum'] . ' руб. не совпадает с суммой необходимой для оплаты товара ' .
                    'стоимостью ' . $totalAmount . ' руб.');
            }

            $checkResult = $this->check($params);
            if ($checkResult !== true) {
                return $this->getResponseError($checkResult);
            }

            return $this->getResponseSuccess('CHECK is successful');
        } elseif ($method == 'pay') {
            if ($arOrder && $arOrder['order_status_id'] == $this->config->get('payment_unitpay_order_status_id_after_pay')) {
                return $this->getResponseSuccess('Payment has already been paid');
            }

            if (!$arOrder) {
                return $this->getResponseError('Can\'t find order');
            }

            if (number_format($params['orderSum'], 2, '.', '') != $totalAmount) {
                return $this->getResponseError('Сумма оплаты в ' . $params['orderSum'] . ' руб. не совпадает с суммой необходимой для оплаты товара ' .
                    'стоимостью ' . $totalAmount . ' руб.');
            }

            $this->pay($params);

            return $this->getResponseSuccess('PAY is successful');
        } elseif ($method == 'error') {
            if (!$arOrder) {
                return $this->getResponseError('Can\'t find order');
            }

            if ($this->config->get('payment_unitpay_set_error_status')) {
                $this->error($params);
            }

            return $this->getResponseSuccess('ERROR is successful');
        }

        return $this->getResponseError($method . ' not supported');
    }

    public function confirm()
    {
        $this->load->model('checkout/order');
        if ($this->config->get('payment_unitpay_create_order') == '1') $this->model_checkout_order->addOrderHistory($this->session->data['order_id'], $this->config->get('payment_unitpay_order_status_id_after_create'), '', true);
        if ($this->config->get('payment_unitpay_cart_reset') == '1') $this->cart->clear();
    }


    private function getResponseSuccess($message)
    {
        return json_encode(array(
            "jsonrpc" => "2.0",
            "result" => array(
                "message" => $message
            ),
            'id' => 1,
        ));
    }

    private function getResponseError($message)
    {
        return json_encode(array(
            "jsonrpc" => "2.0",
            "error" => array(
                "code" => -32000,
                "message" => $message
            ),
            'id' => 1
        ));
    }

    /**
     * @param $method
     * @param array $params
     * @param $secretKey
     * @return string
     */
    function getSha256SignatureByMethodAndParams($method, array $params, $secretKey)
    {
        $delimiter = '{up}';
        ksort($params);
        unset($params['sign']);
        unset($params['signature']);

        return hash('sha256', $method . $delimiter . join($delimiter, $params) . $delimiter . $secretKey);
    }

    private function check($params)
    {
        if ($this->model_checkout_order->getOrder($params['account'])) {
            return true;
        }
        return 'Order not found';
    }

    private function pay($params)
    {
        $new_order_status_id = $this->config->get('payment_unitpay_order_status_id_after_pay');
        $this->model_checkout_order->addOrderHistory($params['account'], $new_order_status_id, 'оплата через UnitPay', true);
    }

    private function error($params)
    {
        $new_order_status_id = $this->config->get('payment_unitpay_order_status_id_error');
        $this->model_checkout_order->addOrderHistory($params['account'], $new_order_status_id, 'ошибка при оплате через UnitPay', false);
    }

    private function getOrderItems($currencyCode, $currencyValue, $data)
    {
        $this->load->model('account/order');
        $orderProducts = $this->model_account_order->getOrderProducts($this->session->data['order_id']);

        $this->load->model('extension/total/coupon');
        $coupon = isset($this->session->data['coupon']) ?
            $this->model_extension_total_coupon->getCoupon($this->session->data['coupon']) :
            null;

        if ($coupon) {
            // Скидка в процентах
            if ($coupon['type'] === 'P') {
                $orderProducts = array_map(function ($item) use ($coupon, $currencyCode, $currencyValue, $data) {
                    return [
                        'name'     => $item['name'],
                        'count'    => $item['quantity'],
                        'price'    => round(($item['price'] - $item['price'] * $coupon['discount'] / 100) * $currencyValue, 2),
                        'currency' => $currencyCode,
						'nds' => $data['payment_unitpay_nds'],
                    ];
                }, $orderProducts);
            }

            // Фиксированная скидка в валюте
            if ($coupon['type'] === 'F') {
                $totalAmount = 0;
                foreach ($orderProducts as $product) {
                    $totalAmount += round($product['price'] * $product['quantity'] * $currencyValue, 2);
                }

                $discountRatio = $coupon['discount'] / $totalAmount;

                $orderProducts = array_map(function ($item) use ($coupon, $discountRatio, $currencyCode, $currencyValue, $data) {
                    return [
                        'name'     => $item['name'],
                        'count'    => $item['quantity'],
                        'price'    => round(($item['price'] - $item['price'] * $discountRatio) * $currencyValue, 2),
                        'currency' => $currencyCode,
						'nds' => $data['payment_unitpay_nds'],
                    ];
                }, $orderProducts);
            }
        } else {
            $orderProducts = array_map(function ($item) use ($currencyCode, $currencyValue, $data) {
                return array(
                    'name'     => $item['name'],
                    'count'    => $item['quantity'],
                    'price'    => round($item['price'] * $currencyValue, 2),
                    'currency' => $currencyCode,
					'nds' => $data['payment_unitpay_nds'],
                );
            }, $orderProducts);
        }

        if (isset($this->session->data['shipping_method']) && $this->session->data['shipping_method']['cost'] > 0) {
			if(!$coupon || $coupon["shipping"] == 0) {
				$orderProducts[] = [
					'name'     => $this->session->data['shipping_method']['title'],
					'count'    => 1,
					'price'    => round($this->session->data['shipping_method']['cost'] * $currencyValue, 2),
					'currency' => $currencyCode,
					'nds' => $data['payment_unitpay_delivery_nds'],
					'type'     => 'service',
				];
			}
        }

        return base64_encode(json_encode($orderProducts));
    }
}

?>