<?php


namespace App\EventSubscriber;


use App\Entity\UserSystem\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\HttpUtils;

/**
 * This event subscriber redirects a user to its settings page, when it needs to change its password or is enforced
 * to setup a 2FA method (enforcement can be set per group).
 * In this cases the user is unable to access sites other than the whitelisted (see ALLOWED_ROUTES).
 * @package App\EventSubscriber
 */
class PasswordChangeNeededSubscriber implements EventSubscriberInterface
{

    protected $security;
    protected $flashBag;
    protected $httpUtils;

    /**
     * @var string[] The routes the user is allowed to access without being redirected.
     * This should be only routes related to login/logout and user settings
     */
    public const ALLOWED_ROUTES = [
        '2fa_login',
        '2fa_login_check',
        'user_settings',
        'club_base_register_u2f',
        'logout',
    ];

    /** @var string The route the user will redirected to, if he needs to change this password */
    public const REDIRECT_TARGET = 'user_settings';

    public function __construct(Security $security, FlashBagInterface $flashBag, HttpUtils $httpUtils)
    {
        $this->security = $security;
        $this->flashBag = $flashBag;
        $this->httpUtils = $httpUtils;
    }

    /**
     * This function is called when the kernel encounters a request.
     * It checks if the user must change its password or add an 2FA mehtod and redirect it to the user settings page,
     * if needed.
     * @param  RequestEvent  $event
     */
    public function redirectToSettingsIfNeeded(RequestEvent $event) : void
    {
        $user = $this->security->getUser();
        $request = $event->getRequest();

        if(!$event->isMasterRequest()) {
            return;
        }
        if(!$user instanceof User) {
            return;
        }

        //Abort if we dont need to redirect the user.
        if (!$user->isNeedPwChange() && !static::TFARedirectNeeded($user)) {
            return;
        }

        //Check for a whitelisted URL
        foreach (static::ALLOWED_ROUTES as $route) {
            //Dont do anything if we encounter an allowed route
            if ($this->httpUtils->checkRequestPath($request, $route)) {
                return;
            }
        }

        /* Dont redirect tree endpoints, as this would cause trouble and creates multiple flash
        warnigs for one page reload */
        if(strpos($request->getUri(), '/tree/') !== false) {
            return;
        }

        //Show appropriate message to user about the reason he was redirected
        if($user->isNeedPwChange()) {
            $this->flashBag->add('warning', 'user.pw_change_needed.flash');
        }

        if(static::TFARedirectNeeded($user)) {
            $this->flashBag->add('warning', 'user.2fa_needed.flash');
        }

        $event->setResponse($this->httpUtils->createRedirectResponse($request, static::REDIRECT_TARGET));

    }

    /**
     * Check if a redirect because of a missing 2FA method is needed.
     * That is the case if the group of the user enforces 2FA, but the user has neither Google Authenticator nor an
     * U2F key setup.
     * @param  User  $user The user for which should be checked if it needs to be redirected.
     * @return bool True if the user needs to be redirected.
     */
    public static function TFARedirectNeeded(User $user) : bool
    {
        $tfa_enabled = $user->isU2FAuthEnabled() || $user->isGoogleAuthenticatorEnabled();

        if ($user->getGroup() !== null && $user->getGroup()->isEnforce2FA() && !$tfa_enabled) {
            return true;
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => 'redirectToSettingsIfNeeded',
        ];
    }
}