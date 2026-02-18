window.devAptMetaInit = function(){
  (function($){
    // remove any previous handlers to avoid double frames
    $(document).off('click.devAptPick');

    function openFrame(multiple, cb, libType){
      var args = {
        title: multiple ? 'Vybrať obrázky' : 'Vybrať súbor',
        button: { text: 'Použiť' },
        multiple: !!multiple
      };
      if (libType) { args.library = { type: libType }; }
      var frame = wp.media(args);
      frame.on('select', function(){
        try{
          var sel = frame.state().get('selection');
          var out = [];
          if (sel && sel.each){ sel.each(function(att){ try{ out.push(att.get('id')); }catch(e){} }); }
          cb(multiple ? out : (out.length ? out[0] : 0));
        }catch(e){}
        frame.close();
      });
      frame.open();
    }

    // Pôdorys (single). Bez MIME filtra -> PDF alebo obrázok.
    $(document).on('click.devAptPick', '.dev-pick-file', function(e){
      e.preventDefault();
      if (typeof wp==='undefined' || !wp.media){ alert('WP media nie je načítané.'); return; }
      var $target = $($(this).data('target'));
      openFrame(false, function(id){ if(id){ $target.val(id).trigger('change'); } });
    });

    $(document).on('click.devAptPick', '.dev-clear-file', function(e){
      e.preventDefault(); var $t=$($(this).data('target')); $t.val('').trigger('change');
    });

    // Galéria (multiple, len images)
    $(document).on('click.devAptPick', '.dev-pick-gallery', function(e){
      e.preventDefault();
      if (typeof wp==='undefined' || !wp.media){ alert('WP media nie je načítané.'); return; }
      var $target = $($(this).data('target'));
      openFrame(true, function(ids){ if(ids && ids.length){ $target.val(ids.join(',')).trigger('change'); renderGallery(ids); } }, 'image');
    });

    $(document).on('click.devAptPick', '.dev-clear-gallery', function(e){
      e.preventDefault(); var $t=$($(this).data('target')); $t.val('').trigger('change'); renderGallery([]);
    });

    function renderGallery(ids){
      var $p = $('#dev_gallery_preview');
      $p.empty();
      if(!ids || !ids.length) return;
      ids.forEach(function(id){
        var att = wp.media.attachment(id);
        att.fetch().then(function(){
          var at = att.toJSON();
          var url = (at.sizes && at.sizes.thumbnail) ? at.sizes.thumbnail.url : at.url;
          var $img = $('<img>').attr('src', url).css({ height:'60px', width:'auto', border:'1px solid #ddd' });
          $p.append($img);
        });
      });
    }

    // Initial preview if gallery already has ids
    var initVal = $('#apt_gallery_ids').val();
    if (initVal){
      var ids = initVal.split(',').filter(function(v){return v && !isNaN(parseInt(v,10));}).map(function(v){return parseInt(v,10);});
      renderGallery(ids);
    }
  })(jQuery);
};

jQuery(function(){ if (typeof window.devAptMetaInit === 'function'){ window.devAptMetaInit(); } });
