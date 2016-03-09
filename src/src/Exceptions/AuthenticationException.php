<?php
    namespace CloudFramework\Service\SocialNetworks\Exceptions;

    class AuthenticationException extends \Exception
    {
        /**
         * Error codes
         *
         *      601     Error fetching OAuth2 access token, client is invalid
         *      602     Error refreshing token + api message
         *
         */
    }