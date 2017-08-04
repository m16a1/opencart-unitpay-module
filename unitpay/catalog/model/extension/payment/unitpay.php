<?php

class ModelExtensionPaymentUnitpay extends Model
{
    public function getMethod($address, $total)
    {
        $this->load->language('extension/payment/unitpay');

        if ($this->config->get('payment_unitpay_status')) {

            $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$this->config->get('payment_unitpay_geo_zone_id') . "' AND country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')");

            $minPrice = (float)$this->config->get('payment_unitpay_min_price');
            if ($minPrice > 0 && $minPrice > $total) {
                $status = false;
            } elseif (!$this->config->get('payment_unitpay_geo_zone_id')) {
                $status = true;
            } elseif ($query->num_rows) {
                $status = true;
            } else {
                $status = false;
            }
        } else {
            $status = false;
        }

        $method_data = array();

        if ($status) {
            $method_data = array(
                'code' => 'unitpay',
                'title' => $this->language->get('text_title'),
                'terms' => '',
                'sort_order' => 0
            );
        }

        return $method_data;
    }
}

?>