<?php
namespace Mvpcode\Leads;

use Bitrix\Main\Application;

/**
 * UTM-метка первого захода. Ставится в cookie на любой странице с ?utm_source=..., читается при заказе —
 * так в сделке Битрикс24 видно, какая реклама привела покупателя. Вызывается из main:OnPageStart.
 */
class Utm
{
    private const COOKIE = 'MVPCODE_UTM_SOURCE';

    public static function rememberFromRequest(): void
    {
        $request = Application::getInstance()->getContext()->getRequest();
        $utm = (string)$request->getQuery('utm_source');
        if ($utm === '' || !empty($_COOKIE[self::COOKIE]) || headers_sent()) {
            return;
        }
        // нативный setcookie: на OnPageStart заголовки ещё не отправлены, а в legacy-страницах
        // куки из D7 Response не всегда успевают уйти
        $value = mb_substr(preg_replace('/[^\w\-.]/u', '', $utm) ?? '', 0, 128);
        setcookie(self::COOKIE, $value, ['expires' => time() + 60 * 60 * 24 * 30, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
        $_COOKIE[self::COOKIE] = $value;
    }

    public static function fromCookie(): string
    {
        return (string)($_COOKIE[self::COOKIE] ?? '');
    }
}
