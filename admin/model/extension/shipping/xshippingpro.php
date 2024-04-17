<?php
class ModelExtensionShippingXshippingpro extends Model {
    use OCM\Traits\Back\Model\Crud;
    use OCM\Traits\Back\Model\Product;
    private $name = 'xshippingpro';
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
}