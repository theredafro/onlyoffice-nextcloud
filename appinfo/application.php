<?php
/**
 *
 * (c) Copyright Ascensio System SIA 2020
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 */

namespace OCA\Onlyoffice\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\DirectEditing\RegisterDirectEditorEvent;

use Psr\Container\ContainerInterface;

use OCA\Viewer\Event\LoadViewer;
use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\Files_Sharing\Event\BeforeTemplateRenderedEvent;

use OCA\Onlyoffice\AppConfig;
use OCA\Onlyoffice\Controller\CallbackController;
use OCA\Onlyoffice\Controller\EditorController;
use OCA\Onlyoffice\Controller\SettingsController;
use OCA\Onlyoffice\Crypt;
use OCA\Onlyoffice\DirectEditor;
use OCA\Onlyoffice\Hooks;
use OCA\Onlyoffice\Listeners\LoadViewerListener;
use OCA\Onlyoffice\Listeners\FilesLoadListener;
use OCA\Onlyoffice\Listeners\FilesSharingLoadListener;
use OCA\Onlyoffice\Listeners\DirectEditorListener;

class Application extends App implements IBootstrap {

    /**
     * Application configuration
     *
     * @var AppConfig
     */
    public $appConfig;

    /**
     * Hash generator
     *
     * @var Crypt
     */
    public $crypt;

    public function __construct(array $urlParams = []) {
        $appName = "onlyoffice";

        parent::__construct($appName, $urlParams);

        $this->appConfig = new AppConfig($appName);
        $this->crypt = new Crypt($this->appConfig);
    }

    public function register(IRegistrationContext $context): void {
        if (class_exists(LoadViewer::class)) {
            $context->registerEventListener(LoadViewer::class, LoadViewerListener::class);
        }

        $context->registerEventListener(LoadAdditionalScriptsEvent::class, FilesLoadListener::class);
        $context->registerEventListener(BeforeTemplateRenderedEvent::class, FilesSharingLoadListener::class);

        $context->registerService('L10N', function (ContainerInterface $c) {
			$server = $c->get(IServerContainer::class);
            return $server->getL10N($c->get("AppName"));
        });
        $context->registerService('RootStorage', function (ContainerInterface $c) {
			$server = $c->get(IServerContainer::class);
            return $server->getRootFolder();
        });
        $context->registerService('UserSession', function (ContainerInterface $c) {
			$server = $c->get(IServerContainer::class);
            return $server->getUserSession();
        });
        $context->registerService('UserManager', function (ContainerInterface $c) {
			$server = $c->get(IServerContainer::class);
            return $server->getUserManager();
        });
        $context->registerService('Logger', function (ContainerInterface $c) {
			$server = $c->get(IServerContainer::class);
            return $server->getLogger();
        });
        $context->registerService('URLGenerator', function (ContainerInterface $c) {
			$server = $c->get(IServerContainer::class);
            return $server->getURLGenerator();
        });
        if (class_exists("OCP\DirectEditing\RegisterDirectEditorEvent")) {
            $context->registerService('DirectEditor', function (ContainerInterface $c) {
                $server = $c->get(IServerContainer::class);
                return new DirectEditor(
                    $c->get("AppName"),
                    $server->getURLGenerator(),
                    $server->getL10N(),
                    $server->getLogger(),
                    $this->appConfig,
                    $this->crypt
                );
            });
            $context->registerEventListener(RegisterDirectEditorEvent::class, DirectEditorListener::class);
        }

        //Controllers
        $context->registerService('CallbackController', function (ContainerInterface $c) {
			$server = $c->get(IServerContainer::class);
            return new CallbackController(
                $c->get("AppName"),
                $server->getRequest(),
                $server->getRootFolder(),
                $server->getUserSession(),
                $server->getUserManager(),
                $server->getL10N(),
                $server->getLogger(),
                $this->appConfig,
                $this->crypt,
                $server->getShareManager()
            );
        });
        $context->registerService('EditorController', function (ContainerInterface $c) {
			$server = $c->get(IServerContainer::class);
            return new EditorController(
                $c->get("AppName"),
                $server->getRequest(),
                $server->getRootFolder(),
                $server->getUserSession(),
                $server->getUserManager(),
                $server->getURLGenerator(),
                $server->getL10N(),
                $server->getLogger(),
                $this->appConfig,
                $this->crypt,
                $server->getShareManager(),
                $server->getSession()
            );
        });
        $context->registerService('SettingsController', function (ContainerInterface $c) {
			$server = $c->get(IServerContainer::class);
            return new SettingsController(
                $c->get("AppName"),
                $server->getRequest(),
                $server->getURLGenerator(),
                $server->getL10N(),
                $server->getLogger(),
                $this->appConfig,
                $this->crypt
            );
        });

        include_once __DIR__ . "/../3rdparty/jwt/BeforeValidException.php";
        include_once __DIR__ . "/../3rdparty/jwt/ExpiredException.php";
        include_once __DIR__ . "/../3rdparty/jwt/SignatureInvalidException.php";
        include_once __DIR__ . "/../3rdparty/jwt/JWT.php";
    }

    public function boot(IBootContext $context): void {
        Hooks::connectHooks();
    }
}