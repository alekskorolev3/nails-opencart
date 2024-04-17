<?php echo $header; ?><?php echo $column_left; ?>
<div id="content">
    <div class="page-header">
      <div class="container-fluid">
      <div class="pull-right float-right">
        <button type="button" id="btn_ocm_save" value="save" data-toggle="tooltip" title="<?php echo $button_save; ?>" class="btn btn-primary"><i class="fa fas fa-save"></i></button>
        <a id="btn_ocm_quick" onclick="return false;" data-toggle="tooltip" title="<?php echo $button_save_continue; ?>" id="btn_ocm_quick" class="btn btn-info"><i class="fa fas fa-clipboard fa-window-restore"></i></a>&nbsp;
        <a href="<?php echo $cancel; ?>" data-toggle="tooltip" title="<?php echo $button_cancel; ?>" class="btn btn-default btn-light"><i class="fa fas fa-reply"></i></a>
      </div>
      <h1><?php echo $heading_title; ?></h1>
      <ul class="breadcrumb">
          <?php foreach($breadcrumbs as $breadcrumb) { ?>
            <li class="breadcrumb-item"><a href="<?php echo $breadcrumb['href']; ?>"><?php echo $breadcrumb['text']; ?></a></li>
          <?php } ?>
      </ul>
      </div>
   </div>
   <div class="container-fluid">
      <?php if ($error_warning) { ?>
        <div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?php echo $error_warning; ?>
          <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
      <?php } ?>
      <?php if ($success) { ?>
        <div class="alert alert-success"><i class="fa fa-exclamation-circle"></i> <?php echo $success; ?>
          <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
      <?php } ?>
      <div class="card panel panel-default">
        <div class="panel-heading card-header">
           <h3 class="panel-title"><i class="fa fas fa-pencil-alt"></i> <?php echo $text_edit; ?></h3>
        </div>
        <div class="panel-body card-body">
          <form action="<?php echo $action; ?>" method="post" enctype="multipart/form-data" id="form-ocm" class="form-horizontal">
            <input type="file" class="ocm-file" accept="text/csv" name="file" />
            <div class="row">
                <div class="col-sm-3">
                   <ul id="ocm-method-list" class="nav flex-column draggable-container">
                     <li class="nav-item" draggable="false"><a draggable="false" href="#tab-global" class="nav-link global" data-toggle="tab"><?php echo $tab_global; ?></a></li>
                      <?php foreach($methods as $no_of_tab => $name) { ?>
                        <li draggable="true" class="nav-item draggable"><a draggable="false" class="nav-link tab<?php echo $no_of_tab; ?>" href="#ocm-method-<?php echo $no_of_tab; ?>" rel="<?php echo $no_of_tab; ?>" data-toggle="tab">
                      <?php echo $name; ?></a><i class="fa fas fa-arrows-alt"></i></li>
                      <?php } ?>
                   </ul>
                  <button class="btn btn-success add-ocm-new" data-toggle="tooltip" form="form-ocm" type="button"  data-placement="bottom"  data-original-title="<?php echo $text_add_new_method; ?>"><i class="fa fa-plus"></i></button>
                </div>
                <div class="col-sm-9">
                    <div id="ocm-container" class="tab-content">
                        <div class="tab-pane active global-tab-content" id="tab-global">
                            <?php echo $global; ?>
                        </div>
                        <?php echo $form_data; ?>
                    </div>
                </div>
            </div>
          </form>
       </div>
     </div>
   </div>
</div>
<link rel="stylesheet" type="text/css" href="view/javascript/ocm/ocm.css?v=1.1.3">
<style type="text/css"> 
.sub-option {
  border: 1px solid #ccc;
  background: #fbfbfb;
  padding: 10px 75px 10px 10px;
  margin-bottom: 15px;
  position: relative;
}
.sub-option .sub-option-action {
  position: absolute;
  top: 40px;
  right: 5px;
  width: 50px;
}
.sub-option table {
  width: 100%;
}
.sub-option table td {
  padding: 5px;
}
.sub-options-btn {
  text-align: right;
}
.drag-sub-option {
  cursor: move !important;
}
.price-range {
    float: left;
    font-weight: bold;
    margin-left: 5px;
    margin-top: 15px;
}
.ocm-range-container.range-cols td.range, .ocm-range-container.product-cols td.product {
    display: table-cell !important;
}
/*  For  OC 3.1 */
<?php if ($oc_3_1==true) { ?>
h3.panel-title {
    font-size: 15px;
    font-weight: normal;
    display: inline-block;
    margin: 0;
    padding: 0;
}
<?php } ?>
</style>
<script type="text/javascript">
var _ocm = {
    token: 'token=<?php echo $token; ?>',
    name: '<?php echo $x_name; ?>',
    path: '<?php echo $x_path; ?>',
    dnd: true, 
    dpTime: '<?php echo $oc_3_1; ?>',
    dashboard: '<?php echo $cancel; ?>'
};
</script>
<script src="view/javascript/ocm/ocm.js?v=1.1.5" type="text/javascript"></script>
<script type="text/javascript"><!--
var ocm_tab;
var unit_row ='<tr rel="{index}">'; 
    unit_row += ' <td class="text-left ocm-hide product">{name}<input type="hidden" name="xshippingpro[ranges][{index}][product_id]" value="{product_id}" /></td>';
    unit_row += '    <td class="text-left ocm-hide range"><input size="15" type="text" name="xshippingpro[ranges][{index}][start]" class="form-control" value="{start}" /></td>';
    unit_row += '    <td class="text-left ocm-hide range"><input size="15" type="text" name="xshippingpro[ranges][{index}][end]" class="form-control" value="{end}" /></td>';
    unit_row += '    <td class="text-left"><input size="15" type="text" name="xshippingpro[ranges][{index}][cost]" class="form-control" value="{cost}" /></td>';
    unit_row += '    <td class="text-left ocm-hide"><input size="6" type="text" name="xshippingpro[ranges][{index}][block]" class="form-control" value="{block}" /></td>';
    unit_row += '    <td class="text-left ocm-hide"><select name="xshippingpro[ranges][{index}][partial]"><option value="0"><?php echo $text_no; ?></option><option value="1"><?php echo $text_yes; ?></option></select></td>';
    unit_row += '    <td class="text-left ocm-hide product"><select name="xshippingpro[ranges][{index}][type]"><option value="quantity"><?php echo $text_rate_quantity; ?></option><option value="weight"><?php echo $text_rate_weight; ?></option><option value="price"><?php echo $text_product_price; ?></option></select></td>';
    unit_row += '    <td class="text-right"><a class="btn btn-sm btn-danger ocm-row-remove"><?php echo $text_remove; ?></a></td>';
    unit_row += '</tr>';

var tpl = <?php echo $tpl; ?>;
var more_help = <?php echo $more_help; ?>;

/* DOM  Event starts */
$(document).ready(function () {
    /* Price Range Options */
    $("#ocm-container").on('click','.add-ocm-row',function() {
        var rate_type = $('#ocm-method-' + ocm_tab).find("select[name^='xshippingpro[rate_type]']").val();
        if (rate_type == 'product') {
            ocm.browser.show({
                type: 'product',
                fn: addProductIntoRanges
            });
        } else {
            var data = {index : ocm.table.next(), start: 0, end: 0, cost: 0, block: 0};
            var _row = ocm.util.interpolate(unit_row, data);
            ocm.table.add(_row);
        }
    });
    
    /* switch between product and generic ranges */
    $("#ocm-container").on('change', "select[name^='xshippingpro[rate_type]']", function() {
        var range_container = $(this).closest('.tab-pane').find('.ocm-range-container');
        if ($(this).val() == 'product') {
            range_container.removeClass('range-cols').addClass('product-cols');
            range_container.find('.delete-all').trigger('click');
        }  else{
            range_container.removeClass('product-cols').addClass('range-cols');
        }
    });
 });
//--></script>
<?php echo $_v; ?>
<?php echo $footer; ?>