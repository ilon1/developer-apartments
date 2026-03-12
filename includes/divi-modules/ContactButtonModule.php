<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Divi modul: Tlačidlo Mám záujem – otvára popup s kontaktným formulárom
 * Plná editovateľnosť štýlu. Popup obsah musí byť na stránke (modul Kód s [bitform id='2'] v overlay).
 */
class DEV_Contact_Button_Module extends ET_Builder_Module {

    public $slug       = 'dev_contact_button';
    public $vb_support = 'partial';

    public function init(){
        $this->name = 'Tlačidlo Mám záujem';
        $this->folder_name = 'dev_apt_byty';
        $this->settings_modal_toggles = array(
            'general' => array( 'toggles' => array( 'main' => 'Text', 'form' => 'Formulár', 'design' => 'Štýl tlačidla', 'align' => 'Zarovnanie' ) ),
        );
    }

    public function get_fields(){
        return array(
            'button_text' => array(
                'label'       => 'Text tlačidla',
                'type'        => 'text',
                'default'     => 'Mám záujem',
                'tab_slug'    => 'general',
                'toggle_slug' => 'main',
            ),
            'form_shortcode' => array(
                'label'       => 'Shortcode formulára',
                'type'        => 'textarea',
                'default'     => "[bitform id='2']",
                'description' => 'Napríklad: [bitform id="2"] alebo [contact-form-7 id="123" title="Kontakt"]',
                'tab_slug'    => 'general',
                'toggle_slug' => 'form',
            ),
            'button_bg' => array(
                'label'     => 'Pozadie',
                'type'      => 'color-alpha',
                'default'   => '#9C9B7F',
                'tab_slug'  => 'general',
                'toggle_slug'=> 'design',
            ),
            'button_color' => array(
                'label'     => 'Farba textu',
                'type'      => 'color-alpha',
                'default'   => '#ffffff',
                'tab_slug'  => 'general',
                'toggle_slug'=> 'design',
            ),
            'button_padding' => array(
                'label'     => 'Padding',
                'type'      => 'custom_padding',
                'default'   => '12px|24px|12px|24px',
                'tab_slug'  => 'general',
                'toggle_slug'=> 'design',
            ),
            'button_border_radius' => array(
                'label'     => 'Zaoblenie',
                'type'      => 'range',
                'default'   => '4',
                'unit'      => 'px',
                'range_settings' => array( 'min'=>0, 'max'=>50, 'step'=>1 ),
                'tab_slug'  => 'general',
                'toggle_slug'=> 'design',
            ),
            'button_font_size' => array(
                'label'     => 'Veľkosť písma',
                'type'      => 'range',
                'default'   => '16',
                'unit'      => 'px',
                'range_settings' => array( 'min'=>12, 'max'=>24, 'step'=>1 ),
                'tab_slug'  => 'general',
                'toggle_slug'=> 'design',
            ),
            'button_border_width' => array(
                'label'     => 'Šírka okraja',
                'type'      => 'range',
                'default'   => '0',
                'unit'      => 'px',
                'range_settings' => array( 'min'=>0, 'max'=>10, 'step'=>1 ),
                'tab_slug'  => 'general',
                'toggle_slug'=> 'design',
            ),
            'button_border_color' => array(
                'label'     => 'Farba okraja',
                'type'      => 'color-alpha',
                'tab_slug'  => 'general',
                'toggle_slug'=> 'design',
            ),
            'button_align' => array(
                'label'       => 'Zarovnanie (PC)',
                'type'        => 'select',
                'options'     => array( 'left' => 'Vľavo', 'center' => 'Na stred', 'right' => 'Vpravo' ),
                'default'     => 'left',
                'tab_slug'    => 'general',
                'toggle_slug' => 'align',
            ),
            'button_align_tablet' => array(
                'label'       => 'Zarovnanie (Tablet)',
                'type'        => 'select',
                'options'     => array( 'left' => 'Vľavo', 'center' => 'Na stred', 'right' => 'Vpravo' ),
                'default'     => 'left',
                'tab_slug'    => 'general',
                'toggle_slug' => 'align',
            ),
            'button_align_phone' => array(
                'label'       => 'Zarovnanie (Mobil)',
                'type'        => 'select',
                'options'     => array( 'left' => 'Vľavo', 'center' => 'Na stred', 'right' => 'Vpravo' ),
                'default'     => 'left',
                'tab_slug'    => 'general',
                'toggle_slug' => 'align',
            ),
        );
    }

    public function render( $attrs, $content = null, $render_slug ){
        $text = trim($this->props['button_text'] ?? 'Mám záujem');
        if($text === '') $text = 'Mám záujem';

        $bg    = $this->props['button_bg'] ?? '#9C9B7F';
        $color = $this->props['button_color'] ?? '#ffffff';
        $pad   = $this->props['button_padding'] ?? '12px|24px|12px|24px';
        $radius= $this->props['button_border_radius'] ?? '4';
        $fs    = $this->props['button_font_size'] ?? '16';
        $bw    = $this->props['button_border_width'] ?? '0';
        $bc    = $this->props['button_border_color'] ?? '';

        $pad_arr = array_map('trim', explode('|', $pad));
        $pad_css = isset($pad_arr[0]) ? $pad_arr[0].' '.(isset($pad_arr[1])?$pad_arr[1]:$pad_arr[0]).' '.(isset($pad_arr[2])?$pad_arr[2]:$pad_arr[0]).' '.(isset($pad_arr[3])?$pad_arr[3]:$pad_arr[1]) : '12px 24px';

        $style = 'display:inline-block;box-sizing:border-box;line-height:1.4;vertical-align:middle;font-family:inherit;font-weight:inherit;-webkit-appearance:none;appearance:none;background:'.esc_attr($bg).';color:'.esc_attr($color).';padding:'.esc_attr($pad_css).';border-radius:'.esc_attr($radius).'px;font-size:'.esc_attr($fs).'px;text-decoration:none;border:'.esc_attr($bw).'px solid '.esc_attr($bc ?: 'transparent').';cursor:pointer;';
        $align = in_array( $this->props['button_align'] ?? 'left', array( 'left', 'center', 'right' ), true ) ? $this->props['button_align'] : 'left';
        $align_t = in_array( $this->props['button_align_tablet'] ?? '', array( 'left', 'center', 'right' ), true ) ? $this->props['button_align_tablet'] : $align;
        $align_m = in_array( $this->props['button_align_phone'] ?? '', array( 'left', 'center', 'right' ), true ) ? $this->props['button_align_phone'] : $align_t;
        $uid = 'dev-contact-btn-'.uniqid();
        if ( dev_apt_is_builder() ) {
            return dev_apt_builder_placeholder( 'Tlačidlo Mám záujem', '✉' );
        }
        $wrap_style = 'text-align:'.esc_attr($align).';';
        $resp_css = '';
        if ( $align_t !== $align ) {
            $resp_css .= '@media(max-width:980px){#'.esc_attr($uid).'{text-align:'.esc_attr($align_t).'}}';
        }
        if ( $align_m !== $align_t ) {
            $resp_css .= '@media(max-width:767px){#'.esc_attr($uid).'{text-align:'.esc_attr($align_m).'}}';
        }
        $form_sc = trim( $this->props['form_shortcode'] ?? '' );
        $form_html = $form_sc !== '' ? do_shortcode( $form_sc ) : '';
        $overlay_id = 'dev-apt-contact-overlay-'.uniqid();

        $wrapper_id = $overlay_id . '-wrap';
        $out = '<div class="dev-apt-contact-module et_pb_module" data-overlay-id="'.esc_attr($overlay_id).'">';
        $out .= '<div id="'.esc_attr($uid).'" class="dev-apt-btn-wrap" style="'.esc_attr($wrap_style).'">';
        $out .= '<button type="button" class="dev-apt-open-contact" style="'.esc_attr($style).'">'.esc_html($text).'</button>';
        $out .= '</div>';
        $out .= '<div id="'.esc_attr($wrapper_id).'" class="dev-apt-contact-wrapper" style="display:none;position:fixed;inset:0;z-index:2147483646;background:transparent;pointer-events:none;">';
        $out .= '<div id="'.esc_attr($overlay_id).'" class="dev-apt-contact-overlay" style="position:absolute;inset:0;background:rgba(0,0,0,.6);cursor:pointer;pointer-events:auto;"></div>';
        $out .= '<div class="dev-apt-contact-content" style="position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);background:#fff;padding:30px;max-width:500px;width:90%;max-height:90vh;overflow:auto;border-radius:8px;pointer-events:auto;">';
        $out .= $form_html;
        $out .= '</div></div>';
        $out .= '<script>(function(){var wid=\''.esc_js($wrapper_id).'\';var oid=\''.esc_js($overlay_id).'\';function init(){var w=document.getElementById(wid);var m=document.querySelector(".dev-apt-contact-module[data-overlay-id=\'"+oid+"\']");if(!w||!m)return setTimeout(init,50);function close(){w.style.display="none";if(window._devAptCloseBtn&&window._devAptCloseBtn.parentNode)window._devAptCloseBtn.remove();window._devAptCloseBtn=null}function open(){document.querySelectorAll(".dev-apt-contact-wrapper").forEach(function(el){el.style.display="none"});if(window._devAptCloseBtn&&window._devAptCloseBtn.parentNode)window._devAptCloseBtn.remove();window._devAptCloseBtn=null;if(w.parentNode!==document.body)document.body.appendChild(w);w.style.display="block";var x=document.createElement("button");x.type="button";x.className="dev-apt-close-contact";x.setAttribute("aria-label","Zavrieť");x.innerHTML="&times;";x.style.cssText="position:fixed;top:20px;right:20px;z-index:2147483647;width:44px;height:44px;background:rgba(255,255,255,.95);border:none;border-radius:50%;font-size:24px;cursor:pointer;line-height:1;box-shadow:0 2px 8px rgba(0,0,0,.2);";x.onclick=close;document.body.appendChild(x);window._devAptCloseBtn=x}var b=m.querySelector(".dev-apt-open-contact");var o=w.querySelector(".dev-apt-contact-overlay");b.onclick=open;if(o)o.onclick=function(e){if(e.target===o)close()}}if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",init);else init()})();</script>';
        $out .= '</div>';
        if ( $resp_css !== '' ) $out .= '<style>'.$resp_css.'</style>';
        return $out;
    }
}

new DEV_Contact_Button_Module();
