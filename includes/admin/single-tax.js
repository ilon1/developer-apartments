jQuery(function($){
  function renderList($box, items){
    var $list = $box.find('.dev-ajax-tax-results');
    $list.empty();
    if(!items || !items.length){ $list.hide(); return; }
    items.forEach(function(it){
      var $row = $('<div class="dev-ajax-tax-item"/>').text(it.text).attr('data-id', it.id);
      $list.append($row);
    });
    $list.show();
  }
  $(document).on('input', '.dev-ajax-tax-search', function(){
    var $box = $(this).closest('.dev-ajax-tax-box');
    var tax = $box.data('tax');
    var q = $(this).val();
    if(q.length < 2){ renderList($box, []); return; }
    $.get(DevAptTax.ajaxurl, { action:'dev_tax_search', taxonomy: tax, q: q, _ajax_nonce: DevAptTax.nonce }, function(resp){
      if(resp && resp.success){ renderList($box, resp.data); }
    });
  });
  $(document).on('click', '.dev-ajax-tax-item', function(){
    var $item = $(this), $box = $item.closest('.dev-ajax-tax-box');
    $box.find('.dev-ajax-tax-id').val($item.data('id'));
    $box.find('.dev-ajax-tax-search').val($item.text());
    $box.find('.dev-ajax-tax-results').hide();
  });
  $(document).on('click', function(e){
    if(!$(e.target).closest('.dev-ajax-tax-box').length){ $('.dev-ajax-tax-results').hide(); }
  });
});
