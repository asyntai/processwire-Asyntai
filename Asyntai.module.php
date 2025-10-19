<?php namespace ProcessWire;

/**
 * Asyntai - AI Chatbot
 *
 * AI assistant / chatbot – Provides instant answers to your website visitors
 *
 * @copyright Copyright (c) 2025, Asyntai
 * @license MIT
 *
 */

class Asyntai extends WireData implements Module, ConfigurableModule {

    /**
     * Module information
     */
    public static function getModuleInfo() {
        return array(
            'title' => 'Asyntai AI chatbot',
            'version' => '1.0.0',
            'summary' => 'AI assistant / chatbot – Provides instant answers to your website visitors',
            'author' => 'Asyntai',
            'href' => 'https://asyntai.com/',
            'singular' => true,
            'autoload' => true,
            'icon' => 'comments',
            'requires' => 'ProcessWire>=3.0.0',
        );
    }

    /**
     * Default configuration
     */
    public static function getDefaultConfig() {
        return array(
            'site_id' => '',
            'script_url' => 'https://asyntai.com/static/js/chat-widget.js',
            'account_email' => ''
        );
    }

    /**
     * Populate default config
     */
    public function __construct() {
        foreach(self::getDefaultConfig() as $key => $value) {
            $this->set($key, $value);
        }
    }

    /**
     * Initialize the module
     */
    public function init() {
        // Load saved configuration
        $data = $this->wire('modules')->getModuleConfigData($this);
        foreach($data as $key => $value) {
            $this->set($key, $value);
        }
    }
    
    /**
     * Called when API is ready
     */
    public function ready() {
        // Only inject on frontend, not admin
        $page = $this->wire('page');
        
        if($page->template == 'admin') return;
        
        $siteId = trim((string) $this->site_id);
        if($siteId === '') return;
        
        // Start output buffering to inject script
        ob_start(function($html) use ($siteId) {
            $scriptUrl = trim((string) $this->script_url);
            if($scriptUrl === '') {
                $scriptUrl = 'https://asyntai.com/static/js/chat-widget.js';
            }
            
            $scriptTag = '<script type="text/javascript">'
                . '(function(){'
                . 'var s=document.createElement("script");'
                . 's.async=true;'
                . 's.defer=true;'
                . 's.src=' . json_encode($scriptUrl) . ';'
                . 's.setAttribute("data-asyntai-id",' . json_encode($siteId) . ');'
                . 's.charset="UTF-8";'
                . 'var f=document.getElementsByTagName("script")[0];'
                . 'if(f&&f.parentNode){f.parentNode.insertBefore(s,f);}else{document.head.appendChild(s);}'
                . '})();'
                . '</script>';
            
            if(strpos($html, '</body>') !== false) {
                $html = str_replace('</body>', $scriptTag . '</body>', $html);
            } else {
                $html .= $scriptTag;
            }
            
            return $html;
        });
    }

    /**
     * Handle AJAX requests for save and reset
     */
    protected function handleAjaxRequest() {
        $input = $this->wire('input');
        $action = $input->post('asyntai_action');
        
        // Verify this is actually an AJAX request
        $isAjax = (
            isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
        ) || $input->post('asyntai_action'); // Fallback to our action parameter
        
        if(!$isAjax) {
            return; // Not our AJAX request, let normal processing continue
        }
        
        // Verify the user has permission
        if(!$this->wire('user')->isSuperuser()) {
            $this->sendJson(array('success' => false, 'error' => 'Permission denied'), 403);
            return;
        }

        if($action === 'save') {
            $this->handleSave();
        } elseif($action === 'reset') {
            $this->handleReset();
        } else {
            $this->sendJson(array('success' => false, 'error' => 'Invalid action'), 400);
        }
    }

    /**
     * Handle save action
     */
    protected function handleSave() {
        $input = $this->wire('input');
        $siteId = trim((string) $input->post('site_id'));
        
        if($siteId === '') {
            $this->sendJson(array('success' => false, 'error' => 'missing site_id'), 400);
            return;
        }

        $scriptUrl = trim((string) $input->post('script_url'));
        $accountEmail = trim((string) $input->post('account_email'));

        $data = array('site_id' => $siteId);
        if($scriptUrl !== '') {
            $data['script_url'] = $scriptUrl;
        } else {
            $data['script_url'] = 'https://asyntai.com/static/js/chat-widget.js';
        }
        if($accountEmail !== '') {
            $data['account_email'] = $accountEmail;
        }

        try {
            $this->wire('modules')->saveModuleConfigData($this, $data);
            
            $this->sendJson(array(
                'success' => true,
                'saved' => $data
            ));
        } catch(\Exception $e) {
            $this->sendJson(array(
                'success' => false, 
                'error' => 'Save failed: ' . $e->getMessage()
            ), 500);
        }
    }

    /**
     * Handle reset action
     */
    protected function handleReset() {
        $data = array(
            'site_id' => '',
            'script_url' => 'https://asyntai.com/static/js/chat-widget.js',
            'account_email' => ''
        );
        
        $this->wire('modules')->saveModuleConfigData($this, $data);
        
        $this->sendJson(array('success' => true));
    }

    /**
     * Send JSON response
     */
    protected function sendJson($data, $status = 200) {
        // Clean any output buffers to prevent HTML from being sent
        while(ob_get_level()) {
            ob_end_clean();
        }
        
        // Set headers
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
        
        // Send JSON and exit immediately
        echo json_encode($data);
        exit;
    }

    /**
     * Build configuration form
     */
    public function getModuleConfigInputfields(array $data) {
        // Handle AJAX requests FIRST, before any HTML is generated
        $input = $this->wire('input');
        if($input->post('asyntai_action')) {
            $this->handleAjaxRequest();
            // handleAjaxRequest will exit, so we never reach here
        }
        
        $modules = $this->wire('modules');
        $inputfields = $modules->get('InputfieldWrapper');
        
        $siteId = isset($data['site_id']) ? trim((string) $data['site_id']) : '';
        $accountEmail = isset($data['account_email']) ? trim((string) $data['account_email']) : '';
        $scriptUrl = isset($data['script_url']) ? trim((string) $data['script_url']) : 'https://asyntai.com/static/js/chat-widget.js';
        $connected = $siteId !== '';

        // Add custom markup for status and connection UI
        $markup = $modules->get('InputfieldMarkup');
        $markup->label = $this->_('Asyntai Connection');
        $markup->icon = 'comments';
        
        $statusColor = $connected ? '#008a20' : '#a00';
        $statusText = $connected ? 'Connected' : 'Not connected';
        $statusExtra = ($connected && $accountEmail) ? ' as ' . $this->wire('sanitizer')->entities($accountEmail) : '';
        
        $resetBtn = $connected ? '<button type="button" id="asyntai-reset" class="ui-button ui-state-default" style="margin-left:8px;">Reset</button>' : '';
        
        $html = '<div id="asyntai-settings-wrap">';
        $html .= '<p id="asyntai-status">Status: <span style="color:' . $statusColor . ';">' . $statusText . '</span>' . $statusExtra . $resetBtn . '</p>';
        $html .= '<div id="asyntai-alert" class="NoticeMessage" style="display:none;margin:10px 0;"></div>';
        
        // Connected box
        $html .= '<div id="asyntai-connected-box" style="display:' . ($connected ? 'block' : 'none') . ';">';
        $html .= '<div style="max-width:820px;margin:20px auto;padding:20px;border:1px solid #ddd;border-radius:8px;background:#fff;text-align:center;">';
        $html .= '<div style="font-size:20px;font-weight:700;margin-bottom:8px;">Asyntai is now enabled</div>';
        $html .= '<div style="font-size:16px;margin-bottom:16px;">Set up your AI chatbot, review chat logs and more:</div>';
        $html .= '<a class="ui-button ui-state-default ui-priority-primary" style="padding:8px 16px;text-decoration:none;" href="https://asyntai.com/dashboard" target="_blank" rel="noopener">Open Asyntai Panel</a>';
        $html .= '<div style="margin-top:16px;font-size:14px;color:#666;">';
        $html .= '<strong>Tip:</strong> If you want to change how the AI answers, please <a href="https://asyntai.com/dashboard#setup" target="_blank" rel="noopener" style="color:#3182ce;text-decoration:underline;">go here</a>.';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        // Connect popup UI
        $html .= '<div id="asyntai-popup-wrap" style="display:' . ($connected ? 'none' : 'block') . ';">';
        $html .= '<div style="max-width:960px;margin:20px auto;padding:24px;border:1px solid #ddd;border-radius:8px;background:#fff;text-align:center;">';
        $html .= '<div style="font-size:18px;margin-bottom:12px;">Create a free Asyntai account or sign in to enable the chatbot</div>';
        $html .= '<button type="button" id="asyntai-connect-btn" class="ui-button ui-state-default ui-priority-primary" style="padding:8px 16px;">Get started</button>';
        $html .= '<div style="margin-top:12px;color:#666;">If it doesn\'t work, <a href="#" id="asyntai-fallback-link" target="_blank" rel="noopener">open the connect window</a>.</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        $html .= '</div>';
        
        // JavaScript for connection flow
        $ajaxUrl = $this->wire('config')->urls->admin . 'module/edit?name=Asyntai';
        $html .= '<script>
(function(){
    var currentState = null;
    var ajaxUrl = ' . json_encode($ajaxUrl) . ';
    
    function showAlert(msg, ok){
        var el=document.getElementById("asyntai-alert"); if(!el) return;
        el.style.display="block";
        el.className=ok?"NoticeMessage":"NoticeError";
        el.textContent=msg;
    }
    
    function generateState(){
        return "pw_"+Math.random().toString(36).substr(2,9);
    }
    
    function updateFallbackLink(){
        var fallbackLink = document.getElementById("asyntai-fallback-link");
        if(fallbackLink && currentState){
            fallbackLink.href = "https://asyntai.com/wp-auth?platform=processwire&state="+encodeURIComponent(currentState);
        }
    }
    
    function openPopup(){
        currentState = generateState();
        updateFallbackLink();
        var base="https://asyntai.com/wp-auth?platform=processwire";
        var url=base+(base.indexOf("?")>-1?"&":"?")+"state="+encodeURIComponent(currentState);
        var w=800,h=720;var y=window.top.outerHeight/2+window.top.screenY-(h/2);var x=window.top.outerWidth/2+window.top.screenX-(w/2);
        var pop=window.open(url,"asyntai_connect","toolbar=no,location=no,status=no,menubar=no,scrollbars=yes,resizable=yes,width="+w+",height="+h+",top="+y+",left="+x);
        if(!pop){ showAlert("Popup blocked. Please allow popups or use the link below.", false); return; }
        pollForConnection(currentState);
    }
    
    // Initialize fallback link on page load
    currentState = generateState();
    updateFallbackLink();
    
    function pollForConnection(state){
        var attempts=0;
        function check(){
            if(attempts++>60) return;
            var script=document.createElement("script");
            var cb="asyntai_cb_"+Date.now();
            script.src="https://asyntai.com/connect-status.js?state="+encodeURIComponent(state)+"&cb="+cb;
            window[cb]=function(data){ try{ delete window[cb]; }catch(e){}
                if(data && data.site_id){ saveConnection(data); return; }
                setTimeout(check, 500);
            };
            script.onerror=function(){ setTimeout(check, 1000); };
            document.head.appendChild(script);
        }
        setTimeout(check, 800);
    }
    
    function saveConnection(data){
        showAlert("Asyntai connected. Saving…", true);
        var payload = new FormData();
        payload.append("asyntai_action", "save");
        payload.append("site_id", data.site_id||"");
        if(data.script_url) payload.append("script_url", data.script_url);
        if(data.account_email) payload.append("account_email", data.account_email);
        
        fetch(ajaxUrl, {
            method:"POST",
            credentials:"same-origin",
            headers:{"X-Requested-With":"XMLHttpRequest"},
            body: payload
        }).then(function(r){ if(!r.ok) throw new Error("HTTP "+r.status); return r.json(); })
        .then(function(json){
            if(!json || !json.success) throw new Error(json && json.error || "Save failed");
            showAlert("Asyntai connected. Chatbot enabled on all pages.", true);
            var status=document.getElementById("asyntai-status");
            if(status){
                var html="Status: <span style=\"color:#008a20;\">Connected</span>";
                if(data.account_email){ html+=" as "+data.account_email; }
                html += " <button type=\"button\" id=\"asyntai-reset\" class=\"ui-button ui-state-default\" style=\"margin-left:8px;\">Reset</button>";
                status.innerHTML=html;
                attachResetHandler();
            }
            var box=document.getElementById("asyntai-connected-box"); if(box) box.style.display="block";
            var wrap=document.getElementById("asyntai-popup-wrap"); if(wrap) wrap.style.display="none";
        }).catch(function(err){ showAlert("Could not save settings: "+(err && err.message || err), false); });
    }
    
    function resetConnection(){
        if(!confirm("Are you sure you want to disconnect Asyntai?")) return;
        var payload = new FormData();
        payload.append("asyntai_action", "reset");
        
        fetch(ajaxUrl, { 
            method:"POST", 
            credentials:"same-origin",
            headers:{"X-Requested-With":"XMLHttpRequest"},
            body: payload
        })
        .then(function(r){ if(!r.ok) throw new Error("HTTP "+r.status); return r.json(); })
        .then(function(){ window.location.reload(); })
        .catch(function(err){ showAlert("Reset failed: "+(err && err.message || err), false); });
    }
    
    function attachResetHandler(){
        var resetBtn = document.getElementById("asyntai-reset");
        if(resetBtn && !resetBtn.hasAttribute("data-attached")){
            resetBtn.setAttribute("data-attached", "true");
            resetBtn.addEventListener("click", function(e){ e.preventDefault(); resetConnection(); });
        }
    }
    
    document.addEventListener("click", function(ev){ 
        var t=ev.target; 
        if(t && t.id==="asyntai-connect-btn"){ ev.preventDefault(); openPopup(); }
        if(t && t.id==="asyntai-reset"){ ev.preventDefault(); resetConnection(); }
        if(t && t.id==="asyntai-fallback-link"){ 
            // Re-generate state and update link when clicked
            currentState = generateState();
            updateFallbackLink();
            // Let the link work normally (target="_blank")
            // Also start polling for this state
            setTimeout(function(){ pollForConnection(currentState); }, 1000);
        }
    });
    
    // Attach handler on initial load if reset button exists
    attachResetHandler();
})();
</script>';
        
        $markup->value = $html;
        $inputfields->add($markup);

        return $inputfields;
    }
}

