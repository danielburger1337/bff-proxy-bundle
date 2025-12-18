[![PHPUnit](https://github.com/danielburger1337/bff-proxy-bundle/actions/workflows/phpunit.yaml/badge.svg)](https://github.com/danielburger1337/bff-proxy-bundle/actions/workflows/phpunit.yaml)
[![PHPStan](https://github.com/danielburger1337/bff-proxy-bundle/actions/workflows/phpstan.yaml/badge.svg)](https://github.com/danielburger1337/bff-proxy-bundle/actions/workflows/phpstan.yaml)
[![PHPCSFixer](https://github.com/danielburger1337/bff-proxy-bundle/actions/workflows/phpcsfixer.yaml/badge.svg)](https://github.com/danielburger1337/bff-proxy-bundle/actions/workflows/phpcsfixer.yaml)

# danielburger1337/bff-proxy-bundle

A [Symfony](https://symfony.com/) bundle that implements the proxy for the Backend for Frontends pattern.

The approach this bundle takes is one of composition. It does not provide all of the required functionality of the BFF pattern on its own.
Mainly, the OAuth2 token management and authentication at the upstream service is not included in this bundle.

## Installation

This library is [PSR-4](https://www.php-fig.org/psr/psr-4/) compatible and can be installed via PHP's dependency manager [Composer](https://getcomposer.org).

```shell
composer require danielburger1337/bff-proxy-bundle
```

## Documentation

TODO

## Configuration reference

```yaml
# config/packages/bff_proxy.yaml
bff_proxy:
    # [Optional] Proxy requests to local API
    local_proxy: local # FALSE will disable feature
    # [Optional] Query parameter to configure runtime options of the proxy
    options_parameter: bff_proxy

    upstreams:
        first-upstream:
            http_client: "@psr18_client_implementation"
            request_factory: "@psr17_factory_implementation"
            stream_factory: "@psr17_factory_implementation"
            http_foundation_factory: "@psr7_to_httpfoundation_bridge"

            # Forward all headers that start with "X-" to the upstream
            passthrough_request_x_headers: false
            # Forward specific headers to the upstream
            passthrough_request_headers: ["my-custom-header"]

            # Proxy all headers that start with "X-" from the upstream to the client
            passthrough_response_x_headers: false
            # Proxy specific headers from the upstream to the client
            passthrough_response_headers: ["upstream-custom-header"]
```
