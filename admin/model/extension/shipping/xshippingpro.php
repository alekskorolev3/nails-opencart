<?php
class ModelExtensionShippingXshippingpro extends Model {
   public function addData($data) {
        $row_exist = $this->db->query("SELECT * FROM `" . DB_PREFIX . "xshippingpro` WHERE tab_id = '" . (int)$data['tab_id'] . "'")->row;
        if ($row_exist) {
            $sql = "UPDATE `" . DB_PREFIX . "xshippingpro` SET method_data= '" . $this->db->escape($data['method_data']) . "', sort_order = '".$data['sort_order']."'";
            $sql .="WHERE tab_id = '" . (int)$data['tab_id'] . "'";
        } else {
            $sql = "INSERT INTO `" .DB_PREFIX . "xshippingpro` SET method_data= '" . $this->db->escape($data['method_data']) . "', sort_order = '".$data['sort_order']."'";
            $sql .= ", `tab_id` = '".(int)$data['tab_id']."'";
        }
        $this->db->query($sql);
        return true;
    }
    /* Backward compatibility */
    private function flattenRagnes($data) {
        $ranges = array();
        $rate_start = $data['rate_start'];
        $rate_end = $data['rate_end'];
        $rate_total = $data['rate_total'];
        $rate_block = $data['rate_block'];
        $rate_partial = $data['rate_partial'];
        foreach($rate_start as $index => $start) {
            $start = $start;
            $end   = $rate_end[$index];
            $cost  = $rate_total[$index];
            $block  = $rate_block[$index];
            $partial = $rate_partial[$index];
            $ranges[] = array('start' => $start, 'end' => $end, 'cost' => $cost, 'block' => $block, 'partial' => $partial);
        }
        return $ranges;
    }
    public function getUnCompressedData($data) {
        /* Backward compatibility */
        if ($data && strpos($data, '{') === false) {
            $data = @unserialize(@base64_decode($data));
        } else {
            $data = json_decode($data, true);
        }
        if (!is_array($data)) $data = array();

        /* Backward compatibility */
        if (isset($data['rate_start']) && isset($data['rate_end'])) {
            $data['ranges'] = $this->flattenRagnes($data);
        }
        return $data;
    }
    public function getData() {
        $rows = $this->db->query("SELECT * FROM `" . DB_PREFIX . "xshippingpro` order by `sort_order` asc")->rows;
        foreach ($rows as &$row) {
            $row['method_data'] = $this->getUnCompressedData($row['method_data']);
        }
        return $rows;
    }
    public function getDataByTabId($tab_id) {
        $row =  $this->db->query("SELECT * FROM `" . DB_PREFIX . "xshippingpro` WHERE tab_id = '" . (int)$tab_id . "'")->row;
        if ($row) {
            $row['method_data'] = $this->getUnCompressedData($row['method_data']);
        }
        return $row;
    }
    public function deleteData($tab_id) {
        $this->db->query("DELETE FROM `" . DB_PREFIX . "xshippingpro` WHERE tab_id = '" . (int)$tab_id . "'");
        return true;
    }
    public function getBatchProductName($ids = array()) {
        if (!$ids) return array();
        $return = array();
        $sql = "SELECT `p`.`product_id`, `pd`.`name` FROM " . DB_PREFIX . "product p";
        $sql .= " LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id) WHERE pd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND p.product_id IN (".implode(',', $ids).")";
        $products = $this->db->query($sql)->rows;
        foreach ($products as $product) {
            $return[$product['product_id']] = $product['name'];
        }
        return $return;
    }
    public function getProducts($data = array()) {
         $sql = "SELECT `p`.`product_id`, `p`.`price`, `pd`.`name` FROM " . DB_PREFIX . "product_to_category p2c";
         $sql .= " LEFT JOIN " . DB_PREFIX . "product p ON (p2c.product_id = p.product_id)";
         $sql .= " LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id) LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) WHERE pd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND p.status = '1'";

         /* TODO  p2s.store_id = '" . (int)$this->config->get('config_store_id') . "' */

         if (!empty($data['filter_name'])) {
            $sql .= " AND pd.name LIKE '" . $this->db->escape($data['filter_name']) . "%'";
         }
         if (!empty($data['filter_model'])) {
            $sql .= " AND p.model LIKE '" . $this->db->escape($data['filter_model']) . "%'";
         }
         if (!empty($data['filter_sku'])) {
            $sql .= " AND p.sku = '" . $this->db->escape($data['filter_sku']) . "'";
         }
         if (!empty($data['filter_jan'])) {
            $sql .= " AND p.jan = '" . $this->db->escape($data['filter_jan']) . "'";
         }
         if (isset($data['filter_manufacturer']) && $data['filter_manufacturer'] !== '') {
            $sql .= " AND p.manufacturer_id = '" . (int)$data['filter_manufacturer'] . "'";
         }
         if (isset($data['filter_category']) && $data['filter_category'] !== '') {
            $sql .= " AND p2c.category_id = '" . (int)$data['filter_category'] . "'";
         }

         $sql .= " GROUP BY p.product_id";
         $sort_data = array(
            'pd.name',
            'p.model',
            'p.quantity',
            'p.price',
            'rating',
            'p.sort_order',
            'p.date_added'
         );

        if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
           if ($data['sort'] == 'pd.name' || $data['sort'] == 'p.model') {
                $sql .= " ORDER BY LCASE(" . $data['sort'] . ")";
           } else {
               $sql .= " ORDER BY " . $data['sort'];
           }
        } else {
            $sql .= " ORDER BY p.sort_order";
        }

        if (isset($data['order']) && ($data['order'] == 'DESC')) {
            $sql .= " DESC, LCASE(pd.name) DESC";
        } else {
            $sql .= " ASC, LCASE(pd.name) ASC";
        }

        if (isset($data['start']) || isset($data['limit'])) {
            if ($data['start'] < 0) {
                $data['start'] = 0;
            }
            if ($data['limit'] < 1) {
                $data['limit'] = 20;
            }
            $sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
        }
        return $this->db->query($sql)->rows;
    }
    public function addDBTables() {
        $sql = "
            CREATE TABLE IF NOT EXISTS `".DB_PREFIX."xshippingpro` (
              `id` int(8) NOT NULL AUTO_INCREMENT,
              `method_data` MEDIUMTEXT NULL,
              `tab_id` int(8) NULL,
              `sort_order` int(8) NULL,
               PRIMARY KEY (`id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
        ";
        $query = $this->db->query($sql);
    }
    
    public function removeDBTables() {
        $query = $this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX."xshippingpro`");
    }
}