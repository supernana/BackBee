<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace BackBuilder\Security\Logout;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Logout\LogoutHandlerInterface;

/**
 * Handler for clearing invalidating the current session.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class SessionLogoutHandler implements LogoutHandlerInterface
{
    /**
     * Invalidate the current session
     *
     * @codeCoverageIgnore
     * @param Request        $request
     * @param Response       $response
     * @param TokenInterface $token
     */
    public function logout(Request $request, Response $response, TokenInterface $token)
    {
        $request->getSession()->invalidate();
    }
}