<?php

declare(strict_types=1);

namespace Volt\Core\System\Controllers;

use CodeIgniter\Controller;
use Volt\Core\Config\Lang\LangService;
use Volt\Core\System\Services\SystemSettingService;

class SystemSettingController extends Controller
{
    private SystemSettingService $settingService;

    public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        helper(['form', 'url']);
        $this->settingService = service('voltSystemSetting');
    }

    public function index(): string
    {
        $actor = service('voltAuth')->currentUser();
        $saved = session()->getFlashdata('settings_saved');

        $settings = $this->settingService->all();

        $sessionLang = session()->get('volt_language');
        if (is_string($sessionLang) && $sessionLang !== '') {
            $settings['language'] = $sessionLang;
        }
        $sessionTz = session()->get('volt_timezone');
        if (is_string($sessionTz) && $sessionTz !== '') {
            $settings['timezone'] = $sessionTz;
        }

        $supportedLangs = LangService::supported();

        $currentLang = $settings['language'] ?? 'en';
        LangService::load($currentLang);
        $lang = LangService::load();

        $content = view('Volt\\Core\\System\\Views\\system_settings', [
            'settings'      => $settings,
            'supportedLangs' => $supportedLangs,
            'saved'         => $saved,
            'lang'          => $lang,
        ]);

        return view('Volt\\Core\\Metadata\\Views\\layouts\\desk', [
            'pageTitle'       => $lang['system']['title'] . ' · Volt Desk',
            'currentUserName' => $actor?->name ?? '',
            'isAdmin'         => $actor?->isAdmin() ?? false,
            'deskActive'      => 'system-settings',
            'content'         => $content,
        ]);
    }

    public function save()
    {
        $language = trim((string) ($this->request->getPost('language') ?? 'en'));
        $timezone = trim((string) ($this->request->getPost('timezone') ?? 'UTC'));

        if ($language !== '') {
            $this->settingService->set('language', $language);
            session()->set('volt_language', $language);
        }

        if ($timezone !== '') {
            $this->settingService->set('timezone', $timezone);
            session()->set('volt_timezone', $timezone);
        }

        LangService::load($language);

        session()->setFlashdata('settings_saved', true);

        return redirect()->to(site_url('desk/system-settings'));
    }
}
