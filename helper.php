<?php

use dokuwiki\Extension\Plugin;
use dokuwiki\Logger as logger;

/**
 * DokuWiki Plugin turnstile (Helper Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author Ye Ziruo <i@yeziruo.com>
 */
class helper_plugin_turnstile extends Plugin
{
    /**
     * getHtml
     * get turnstile components html
     * @return string return html
     */
    public function getHtml(): string
    {
        $site_key = $this->getConf("site_key");
        return '<div class="cf-turnstile" data-sitekey="' . $site_key . '"></div>';
    }

    /**
     * siteVerify
     * server-side validation
     * @return bool verify result
     */
    public function siteVerify(): bool
    {
        global $INPUT;
        $code = $INPUT->post->str('cf-turnstile-response');
        if (empty($code)) {
            msg($this->getLang('uncompleted_captcha'), -1);
            return false;
        }
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, 'https://challenges.cloudflare.com/turnstile/v0/siteverify');
        curl_setopt($curl, CURLOPT_TIMEOUT, 2);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, [
            'secret' => $this->getConf('secret_key'),
            'response' => $code,
        ]);
        $resp = curl_exec($curl);
        if (!curl_errno($curl)) {
            $data = json_decode($resp, true);
            if (!json_last_error()) {
                return $data['success'];
            }
            logger::error('[Turnstile] json_last_error: ' . json_last_error_msg());
        } else {
            logger::error('[Turnstile] curl_error: ' . curl_error($curl));
        }
        curl_close($curl);
        msg($this->getLang('invalid_response'), -1);
        return false;
    }
}
