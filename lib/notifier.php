<?php
/**
 *
 * (c) Copyright Ascensio System SIA 2021
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

namespace OCA\Onlyoffice;

use OCP\IURLGenerator;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IUserManager;
use OCP\Notification\AlreadyProcessedException;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Files\IRootFolder;
use OCP\Share\IManager;

class Notifier implements INotifier {

    /**
     * Application name
     *
     * @var string
     */
    private $appName;

    /**
     * l10n service
     *
     * @var IL10N
     */
    private $trans;

    /**
     * Url generator service
     *
     * @var IURLGenerator
     */
    private $urlGenerator;

    /**
     * Root folder
     *
     * @var IRootFolder
     */
    private $root;

    /**
     * Logger
     *
     * @var ILogger
     */
    private $logger;

    /**
     * Share manager
     *
     * @var IManager
     */
    private $shareManager;

    /**
     * @param string $AppName - application name
     * @param IL10N $trans - l10n service
     * @param IURLGenerator $urlGenerator - url generator service
     * @param ILogger $logger - logger
     * @param IUserManager $userManager - user manager
     * @param IRootFolder $root - root folder
     */
    public function __construct(string $appName,
                                    IL10N $trans,
                                    IURLGenerator $urlGenerator,
                                    ILogger $logger,
                                    IUserManager $userManager,
                                    IRootFolder $root,
                                    IManager $shareManager
                                    ) {
        $this->appName = $appName;
        $this->trans = $trans;
        $this->urlGenerator = $urlGenerator;
        $this->logger = $logger;
        $this->userManager = $userManager;
        $this->root = $root;
        $this->shareManager = $shareManager;
    }

    /**
     * Identifier of the notifier, only use [a-z0-9_]
     *
     * @return string
     */
    public function getID(): string {
        return $this->appName;
    }

    /**
     * Human readable name describing the notifier
     *
     * @return string
     */
    public function getName(): string {
        return $this->appName;
    }
    
    /**
     * @param INotification $notification - notification object
     * @param string $languageCode - the code of the language that should be used to prepare the notification
     * 
     * @return INotification
     */
    public function prepare(INotification $notification, string $languageCode): INotification {
        if ($notification->getApp() !== $this->appName) {
            throw new \InvalidArgumentException("Notification not from " . $this->appName);
        }

        $parameters = $notification->getSubjectParameters();

        $notifierId = $parameters["notifierId"];
        $fileId = $parameters["fileId"];
        $actionLink = $parameters["actionLink"];
        
        $files = [];
        try {
            $files = $this->root->getUserFolder($notifierId)->getById($fileId);
        } catch (\Exception $e) {
            $this->logger->logException($e, ["message" => "Notify prepate: $fileId", "app" => $this->appName]);
        }

        if (empty($files)) {
            $this->logger->info("Notify prepate: file not found: $fileId", ["app" => $this->appName]);
            throw new AlreadyProcessedException();
        }

        $file = $files[0];

        $accessList = $this->shareManager->getAccessList($file);
        if (!in_array($notification->getUser(), $accessList["users"])) {
            throw new AlreadyProcessedException();
        }

        $fileName = $file->getName();

        $notifier = $this->userManager->get($notifierId);
        $notifierName = $notifier->getDisplayName();

        $notification->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath($this->appName, "app-dark.svg")))
            ->setParsedSubject($this->trans->t('%1$s mentioned you in the %2$s: "%3$s".', [$notifierName, $fileName, $notification->getObjectId()]))
            ->setRichSubject($this->trans->t('{notifier} mentioned you in the {file}: "%1$s".', [$notification->getObjectId()]), [
                "notifier" => [
                    "type" => "user",
                    "id" => $notifierId,
                    "name" => $notifierName
                ],
                "file" => [
                    "type" => "highlight",
                    "id" => $fileId,
                    "name" => $fileName
                ]
            ]);

        $editorLink = $this->urlGenerator->linkToRouteAbsolute($this->appName . ".editor.index", ["fileId" => $fileId,
                                                                                                    "actionType" => $actionLink["action"]["type"],
                                                                                                    "actionData" => $actionLink["action"]["data"]]);

        $notification->setLink($editorLink);

        return $notification;
    }
}