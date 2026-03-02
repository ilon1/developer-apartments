/* includes/admin/apt-meta.js
 * Media pickery + galéria + AUTO výpočet „Plocha celkom“
 * Robustné voči Gutenberg re‑mountu (reinit, MutationObserver)
 */
window.devAptMetaInit = function () {
  (function ($) {
    // ---- Pomocné funkcie ----
    function toNum(v) {
      var n = parseFloat(String(v).replace(',', '.'));
      return isNaN(n) ? 0 : n;
    }
    function round2(n) { return Math.round(n * 100) / 100; }

    function log() {
      if (window.devAptDebug) {
        try { console.debug.apply(console, ['[dev-apt]'].concat([].slice.call(arguments))); } catch (e) {}
      }
    }

    // =======================
    //  MEDIA PICKERY (pôdorys + galéria)
    // =======================
    // Zruš predchádzajúce handlery, aby sa nezdvojovali pri reinit-e
    $(document).off('click.devAptPick');

    function openFrame(multiple, cb, libType) {
      var args = {
        title: multiple ? 'Vybrať obrázky' : 'Vybrať súbor',
        button: { text: 'Použiť' },
        multiple: !!multiple
      };
      if (libType) args.library = { type: libType };
      var frame = wp.media(args);
      frame.on('select', function () {
        try {
          var sel = frame.state().get('selection'), out = [];
          if (sel && sel.each) {
            sel.each(function (att) { try { out.push(att.get('id')); } catch (e) {} });
          }
          cb(multiple ? out : (out.length ? out[0] : 0));
        } catch (e) {}
        frame.close();
      });
      frame.open();
    }

    // Pôdorys (single)
    $(document).on('click.devAptPick', '.dev-pick-file', function (e) {
      e.preventDefault();
      if (typeof wp === 'undefined' || !wp.media) { alert('WP media nie je načítané.'); return; }
      var $target = $($(this).data('target'));
      openFrame(false, function (id) { if (id) { $target.val(id).trigger('change'); } });
    });
    $(document).on('click.devAptPick', '.dev-clear-file', function (e) {
      e.preventDefault();
      var $t = $($(this).data('target'));
      $t.val('').trigger('change');
    });

    // Galéria (multiple, len images)
    $(document).on('click.devAptPick', '.dev-pick-gallery', function (e) {
      e.preventDefault();
      if (typeof wp === 'undefined' || !wp.media) { alert('WP media nie je načítané.'); return; }
      var $target = $($(this).data('target'));
      openFrame(true, function (ids) {
        if (ids && ids.length) {
          $target.val(ids.join(',')).trigger('change');
          renderGallery(ids);
        }
      }, 'image');
    });
    $(document).on('click.devAptPick', '.dev-clear-gallery', function (e) {
      e.preventDefault();
      var $t = $($(this).data('target'));
      $t.val('').trigger('change');
      renderGallery([]);
    });

    function renderGallery(ids) {
      var $p = $('#dev_gallery_preview');
      $p.empty();
      if (!ids || !ids.length) return;
      ids.forEach(function (id) {
        var att = wp.media.attachment(id);
        att.fetch().then(function () {
          var at = att.toJSON();
          var url = (at.sizes && at.sizes.thumbnail) ? at.sizes.thumbnail.url : at.url;
          var $img = $('<img>').attr('src', url).css({ height: '60px', width: 'auto', border: '1px solid #ddd' });
          $p.append($img);
        });
      });
    }

    // Po načítaní obnov galériu, ak sú v hidden input-e IDs
    (function initGalleryFromHidden() {
      var initVal = $('#apt_gallery_ids').val();
      if (initVal) {
        var ids = initVal.split(',')
          .map(function (v) { return parseInt(v, 10); })
          .filter(function (n) { return !isNaN(n); });
        if (ids.length) renderGallery(ids);
      }
    })();

    // =======================
    //  AUTO VÝPOČET „Plocha celkom (m²)“
    // =======================
    var sel = {
      i: '#apt_area_interior',
      e: '#apt_area_exterior',
      c: '#apt_cellar_area',
      cYes: '#apt_cellar_yes',
      t: '#apt_area_total'
    };

    function getInputs() {
      return {
        $i: $(sel.i),
        $e: $(sel.e),
        $c: $(sel.c),
        $cYes: $(sel.cYes),
        $t: $(sel.t)
      };
    }

    // Vloží reset tlačidlo vedľa "Plocha celkom" – len raz
    function ensureReset($t) {
      if (!$t || !$t.length) return;
      if ($t.next('.dev-apt-reset-auto').length) return;
      var $btn = $('<button type="button" class="button button-small dev-apt-reset-auto" style="margin-left:8px;">✳︎ Reset auto</button>')
        .attr('title', 'Obnoviť automatický výpočet Plocha celkom');
      $t.after($btn);
      $btn.on('click', function () {
        $t.removeAttr('data-manual');
        recalcTotal(true /*force*/);
      });
    }

    function markManual($t, on) {
      if (!$t || !$t.length) return;
      if (on) $t.attr('data-manual', '1'); else $t.removeAttr('data-manual');
    }

    function recalcTotal(force) {
      var refs = getInputs(), $t = refs.$t;
      if (!$t.length) return;

      if (!force && $t.attr('data-manual') === '1') {
        log('skip auto (manual override)');
        return;
      }

      var i = toNum(refs.$i.val());
      var e = toNum(refs.$e.val());
      var cVal = toNum(refs.$c.val());
      var cOn = refs.$cYes.is(':checked');

      // Iba Áno -> pivnica sa NEpočíta
      var total = i + e + (cOn ? 0 : cVal);
      if (total > 0) $t.val(round2(total)); else $t.val('');
      log('recalc total:', { i: i, e: e, c: cVal, cOn: cOn, total: $t.val() });
    }

    function bindAutoTotal() {
      var refs = getInputs();
      if (!refs.$t.length) { log('no total input found'); return; }

      ensureReset(refs.$t);

      // Ak užívateľ ručne editne Plochu celkom -> manuálny režim
      refs.$t.off('.devAptTotal').on('input.devAptTotal change.devAptTotal', function () {
        markManual(refs.$t, true);
      });

      // Pri zmene i/e/c/checkbox -> prepočet
      [refs.$i, refs.$e, refs.$c, refs.$cYes].forEach(function ($el) {
        if ($el && $el.length) {
          $el.off('.devAptTotal').on('input.devAptTotal change.devAptTotal', function () {
            recalcTotal(false);
          });
        }
      });

      // First run: ak je total prázdny, dopočítaj
      if (!refs.$t.val()) {
        recalcTotal(true);
      }
    }

    // Prvé naviazanie
    bindAutoTotal();

    // =======================
    //  Gutenberg re‑mount guard
    // =======================
    // Niekedy Gutenberg re‑mountne metabox (DOM sa znova vykreslí).
    // Sledujeme zmeny a pri objavení/zmene inputov bindneme znova.
    try {
      var observer = new MutationObserver(function (mut) {
        // ak pribudlo niečo s niektorým z našich ID, rebinding
        var need = mut.some ? mut : Array.prototype.slice.call(mut);
        var hit = need.some(function (m) {
          if (!m.addedNodes || !m.addedNodes.length) return false;
          for (var i = 0; i < m.addedNodes.length; i++) {
            var n = m.addedNodes[i];
            if (n.nodeType !== 1) continue;
            if (n.querySelector && (
                n.querySelector(sel.i) || n.querySelector(sel.e) ||
                n.querySelector(sel.c) || n.querySelector(sel.cYes) ||
                n.querySelector(sel.t)
              )) {
              return true;
            }
          }
          return false;
        });
        if (hit) {
          log('remount detected -> rebind total calc');
          bindAutoTotal();
        }
      });
      observer.observe(document.body, { childList: true, subtree: true });
    } catch (e) {
      // ignore
    }

    // DEBUG toggle: ak chceš logy, v konzole zadaj: window.devAptDebug = true;
    log('apt-meta initialized');
  })(jQuery);
};

// Fallback init
jQuery(function () {
  if (typeof window.devAptMetaInit === 'function') {
    window.devAptMetaInit();
  }
});