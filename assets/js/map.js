jQuery(document).ready(function($) {
    function initDevMaps() {
        $('.dev-map-wrapper').each(function() {
            var wrapper = $(this);
            if(wrapper.data('init-done')) return;
            var img = wrapper.find('.dev-map-image');
            var svg = wrapper.find('.dev-frontend-svg');
            var tooltip = wrapper.find('.dev-map-tooltip');
            var config = {
                pos: wrapper.data('tt-pos') || 'top',
                bg: wrapper.data('tt-bg'),
                color: wrapper.data('tt-color'),
                pad: wrapper.data('tt-pad') + 'px',
                rad: wrapper.data('tt-rad') + 'px',
                showCount: wrapper.data('cnt-show') === 'on',
                cntBg: wrapper.data('cnt-bg'),
                cntColor: wrapper.data('cnt-color'),
                cntSize: wrapper.data('cnt-size') + 'px'
            };
            tooltip.css({'background': config.bg,'color': config.color,'padding': config.pad,'border-radius': config.rad});
            var shapesData = svg.attr('data-shapes');
            if (!shapesData) return;
            var shapes = JSON.parse(shapesData);
            function setupAndDraw() {
                var naturalW = img[0].naturalWidth;
                var naturalH = img[0].naturalHeight;
                if (naturalW === 0) { setTimeout(setupAndDraw, 100); return; }
                svg[0].setAttribute('viewBox', '0 0 ' + naturalW + ' ' + naturalH);
                svg.empty();
                shapes.forEach(function(shape) {
                    var poly = document.createElementNS('http://www.w3.org/2000/svg', 'polygon');
                    var pointsStr = shape.points.map(function(p){ return p.join(','); }).join(' ');
                    poly.setAttribute('points', pointsStr);
                    poly.setAttribute('fill', shape.color || '#28a745');
                    poly.setAttribute('fill-opacity', '0.3');
                    poly.setAttribute('stroke', 'transparent');
                    $(poly).on('mouseenter', function(e) {
                        $(this).attr('fill-opacity', '0.6');
                        var html = '<span class="dev-tt-title">' + shape.display_label + '</span>';
                        if (config.showCount) {
                            var countText = shape.free_count + ' voľných';
                            if (shape.target_type === 'post') { countText = (shape.free_count > 0) ? 'Voľný' : 'Obsadený'; }
                            html += '<div class="dev-tt-count" style="background:'+config.cntBg+'; color:'+config.cntColor+'; font-size:'+config.cntSize+';">' + countText + '</div>';
                        }
                        tooltip.html(html).show();
                        moveTooltip(e, config.pos);
                    });
                    $(poly).on('mouseleave', function(){ $(this).attr('fill-opacity','0.3'); tooltip.hide(); });
                    $(poly).on('mousemove', function(e){ moveTooltip(e, config.pos); });
                    $(poly).on('click', function(e){
                        e.preventDefault();
                        var id = shape.target_id;
                        if (shape.target_type === 'term') {
                            var currentUrl = window.location.href.split('?')[0];
                            window.location.href = currentUrl + '?map_id=' + id;
                        } else if (shape.target_type === 'post') {
                            window.location.href = (window.location.origin + window.location.pathname + '?p=') + id;
                        }
                    });
                    svg.append(poly);
                });
                wrapper.data('init-done', true);
            }
            if (img[0].complete) setupAndDraw(); else img.on('load', setupAndDraw);
        });
    }
    function moveTooltip(e, pos) {
        var tt = jQuery('.dev-map-tooltip:visible');
        var offset = 15; var x = e.clientX; var y = e.clientY; var w = tt.outerWidth(); var h = tt.outerHeight();
        var top, left;
        switch(pos){
            case 'bottom': top = y + offset; left = x - (w/2); break;
            case 'left':   top = y - (h/2);   left = x - w - offset; break;
            case 'right':  top = y - (h/2);   left = x + offset; break;
            case 'top':
            default:       top = y - h - offset; left = x - (w/2); break;
        }
        tt.css({ top: top, left: left });
    }
    initDevMaps();
    setTimeout(initDevMaps, 1000);
});
