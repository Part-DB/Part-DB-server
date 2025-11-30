<?php

// This file is auto-generated and is for apps only. Bundles SHOULD NOT rely on its content.

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

/**
 * This class provides array-shapes for configuring the services and bundles of an application.
 *
 * Services declared with the config() method below are autowired and autoconfigured by default.
 *
 * This is for apps only. Bundles SHOULD NOT use it.
 *
 * Example:
 *
 *     ```php
 *     // config/services.php
 *     namespace Symfony\Component\DependencyInjection\Loader\Configurator;
 *
 *     return App::config([
 *         'services' => [
 *             'App\\' => [
 *                 'resource' => '../src/',
 *             ],
 *         ],
 *     ]);
 *     ```
 *
 * @psalm-type ImportsConfig = list<string|array{
 *     resource: string,
 *     type?: string|null,
 *     ignore_errors?: bool,
 * }>
 * @psalm-type ParametersConfig = array<string, scalar|\UnitEnum|array<scalar|\UnitEnum|array<mixed>|null>|null>
 * @psalm-type ArgumentsType = list<mixed>|array<string, mixed>
 * @psalm-type CallType = array<string, ArgumentsType>|array{0:string, 1?:ArgumentsType, 2?:bool}|array{method:string, arguments?:ArgumentsType, returns_clone?:bool}
 * @psalm-type TagsType = list<string|array<string, array<string, mixed>>> // arrays inside the list must have only one element, with the tag name as the key
 * @psalm-type CallbackType = string|array{0:string|ReferenceConfigurator,1:string}|\Closure|ReferenceConfigurator|ExpressionConfigurator
 * @psalm-type DeprecationType = array{package: string, version: string, message?: string}
 * @psalm-type DefaultsType = array{
 *     public?: bool,
 *     tags?: TagsType,
 *     resource_tags?: TagsType,
 *     autowire?: bool,
 *     autoconfigure?: bool,
 *     bind?: array<string, mixed>,
 * }
 * @psalm-type InstanceofType = array{
 *     shared?: bool,
 *     lazy?: bool|string,
 *     public?: bool,
 *     properties?: array<string, mixed>,
 *     configurator?: CallbackType,
 *     calls?: list<CallType>,
 *     tags?: TagsType,
 *     resource_tags?: TagsType,
 *     autowire?: bool,
 *     bind?: array<string, mixed>,
 *     constructor?: string,
 * }
 * @psalm-type DefinitionType = array{
 *     class?: string,
 *     file?: string,
 *     parent?: string,
 *     shared?: bool,
 *     synthetic?: bool,
 *     lazy?: bool|string,
 *     public?: bool,
 *     abstract?: bool,
 *     deprecated?: DeprecationType,
 *     factory?: CallbackType,
 *     configurator?: CallbackType,
 *     arguments?: ArgumentsType,
 *     properties?: array<string, mixed>,
 *     calls?: list<CallType>,
 *     tags?: TagsType,
 *     resource_tags?: TagsType,
 *     decorates?: string,
 *     decoration_inner_name?: string,
 *     decoration_priority?: int,
 *     decoration_on_invalid?: 'exception'|'ignore'|null,
 *     autowire?: bool,
 *     autoconfigure?: bool,
 *     bind?: array<string, mixed>,
 *     constructor?: string,
 *     from_callable?: CallbackType,
 * }
 * @psalm-type AliasType = string|array{
 *     alias: string,
 *     public?: bool,
 *     deprecated?: DeprecationType,
 * }
 * @psalm-type PrototypeType = array{
 *     resource: string,
 *     namespace?: string,
 *     exclude?: string|list<string>,
 *     parent?: string,
 *     shared?: bool,
 *     lazy?: bool|string,
 *     public?: bool,
 *     abstract?: bool,
 *     deprecated?: DeprecationType,
 *     factory?: CallbackType,
 *     arguments?: ArgumentsType,
 *     properties?: array<string, mixed>,
 *     configurator?: CallbackType,
 *     calls?: list<CallType>,
 *     tags?: TagsType,
 *     resource_tags?: TagsType,
 *     autowire?: bool,
 *     autoconfigure?: bool,
 *     bind?: array<string, mixed>,
 *     constructor?: string,
 * }
 * @psalm-type StackType = array{
 *     stack: list<DefinitionType|AliasType|PrototypeType|array<class-string, ArgumentsType|null>>,
 *     public?: bool,
 *     deprecated?: DeprecationType,
 * }
 * @psalm-type ServicesConfig = array{
 *     _defaults?: DefaultsType,
 *     _instanceof?: InstanceofType,
 *     ...<string, DefinitionType|AliasType|PrototypeType|StackType|ArgumentsType|null>
 * }
 * @psalm-type ExtensionType = array<string, mixed>
 * @psalm-type FrameworkConfig = array{
 *     secret?: scalar|null,
 *     http_method_override?: bool, // Set true to enable support for the '_method' request parameter to determine the intended HTTP method on POST requests. // Default: false
 *     allowed_http_method_override?: list<string>|null,
 *     trust_x_sendfile_type_header?: scalar|null, // Set true to enable support for xsendfile in binary file responses. // Default: "%env(bool:default::SYMFONY_TRUST_X_SENDFILE_TYPE_HEADER)%"
 *     ide?: scalar|null, // Default: "%env(default::SYMFONY_IDE)%"
 *     test?: bool,
 *     default_locale?: scalar|null, // Default: "en"
 *     set_locale_from_accept_language?: bool, // Whether to use the Accept-Language HTTP header to set the Request locale (only when the "_locale" request attribute is not passed). // Default: false
 *     set_content_language_from_locale?: bool, // Whether to set the Content-Language HTTP header on the Response using the Request locale. // Default: false
 *     enabled_locales?: list<scalar|null>,
 *     trusted_hosts?: list<scalar|null>,
 *     trusted_proxies?: mixed, // Default: ["%env(default::SYMFONY_TRUSTED_PROXIES)%"]
 *     trusted_headers?: list<scalar|null>,
 *     error_controller?: scalar|null, // Default: "error_controller"
 *     handle_all_throwables?: bool, // HttpKernel will handle all kinds of \Throwable. // Default: true
 *     csrf_protection?: bool|array{
 *         enabled?: scalar|null, // Default: null
 *         stateless_token_ids?: list<scalar|null>,
 *         check_header?: scalar|null, // Whether to check the CSRF token in a header in addition to a cookie when using stateless protection. // Default: false
 *         cookie_name?: scalar|null, // The name of the cookie to use when using stateless protection. // Default: "csrf-token"
 *     },
 *     form?: bool|array{ // Form configuration
 *         enabled?: bool, // Default: true
 *         csrf_protection?: array{
 *             enabled?: scalar|null, // Default: null
 *             token_id?: scalar|null, // Default: null
 *             field_name?: scalar|null, // Default: "_token"
 *             field_attr?: array<string, scalar|null>,
 *         },
 *     },
 *     http_cache?: bool|array{ // HTTP cache configuration
 *         enabled?: bool, // Default: false
 *         debug?: bool, // Default: "%kernel.debug%"
 *         trace_level?: "none"|"short"|"full",
 *         trace_header?: scalar|null,
 *         default_ttl?: int,
 *         private_headers?: list<scalar|null>,
 *         skip_response_headers?: list<scalar|null>,
 *         allow_reload?: bool,
 *         allow_revalidate?: bool,
 *         stale_while_revalidate?: int,
 *         stale_if_error?: int,
 *         terminate_on_cache_hit?: bool,
 *     },
 *     esi?: bool|array{ // ESI configuration
 *         enabled?: bool, // Default: false
 *     },
 *     ssi?: bool|array{ // SSI configuration
 *         enabled?: bool, // Default: false
 *     },
 *     fragments?: bool|array{ // Fragments configuration
 *         enabled?: bool, // Default: false
 *         hinclude_default_template?: scalar|null, // Default: null
 *         path?: scalar|null, // Default: "/_fragment"
 *     },
 *     profiler?: bool|array{ // Profiler configuration
 *         enabled?: bool, // Default: false
 *         collect?: bool, // Default: true
 *         collect_parameter?: scalar|null, // The name of the parameter to use to enable or disable collection on a per request basis. // Default: null
 *         only_exceptions?: bool, // Default: false
 *         only_main_requests?: bool, // Default: false
 *         dsn?: scalar|null, // Default: "file:%kernel.cache_dir%/profiler"
 *         collect_serializer_data?: bool, // Enables the serializer data collector and profiler panel. // Default: false
 *     },
 *     workflows?: bool|array{
 *         enabled?: bool, // Default: false
 *         workflows?: array<string, array{ // Default: []
 *             audit_trail?: bool|array{
 *                 enabled?: bool, // Default: false
 *             },
 *             type?: "workflow"|"state_machine", // Default: "state_machine"
 *             marking_store?: array{
 *                 type?: "method",
 *                 property?: scalar|null,
 *                 service?: scalar|null,
 *             },
 *             supports?: list<scalar|null>,
 *             definition_validators?: list<scalar|null>,
 *             support_strategy?: scalar|null,
 *             initial_marking?: list<scalar|null>,
 *             events_to_dispatch?: list<string>|null,
 *             places?: list<array{ // Default: []
 *                 name: scalar|null,
 *                 metadata?: list<mixed>,
 *             }>,
 *             transitions: list<array{ // Default: []
 *                 name: string,
 *                 guard?: string, // An expression to block the transition.
 *                 from?: list<array{ // Default: []
 *                     place: string,
 *                     weight?: int, // Default: 1
 *                 }>,
 *                 to?: list<array{ // Default: []
 *                     place: string,
 *                     weight?: int, // Default: 1
 *                 }>,
 *                 weight?: int, // Default: 1
 *                 metadata?: list<mixed>,
 *             }>,
 *             metadata?: list<mixed>,
 *         }>,
 *     },
 *     router?: bool|array{ // Router configuration
 *         enabled?: bool, // Default: false
 *         resource: scalar|null,
 *         type?: scalar|null,
 *         cache_dir?: scalar|null, // Deprecated: Setting the "framework.router.cache_dir.cache_dir" configuration option is deprecated. It will be removed in version 8.0. // Default: "%kernel.build_dir%"
 *         default_uri?: scalar|null, // The default URI used to generate URLs in a non-HTTP context. // Default: null
 *         http_port?: scalar|null, // Default: 80
 *         https_port?: scalar|null, // Default: 443
 *         strict_requirements?: scalar|null, // set to true to throw an exception when a parameter does not match the requirements set to false to disable exceptions when a parameter does not match the requirements (and return null instead) set to null to disable parameter checks against requirements 'true' is the preferred configuration in development mode, while 'false' or 'null' might be preferred in production // Default: true
 *         utf8?: bool, // Default: true
 *     },
 *     session?: bool|array{ // Session configuration
 *         enabled?: bool, // Default: false
 *         storage_factory_id?: scalar|null, // Default: "session.storage.factory.native"
 *         handler_id?: scalar|null, // Defaults to using the native session handler, or to the native *file* session handler if "save_path" is not null.
 *         name?: scalar|null,
 *         cookie_lifetime?: scalar|null,
 *         cookie_path?: scalar|null,
 *         cookie_domain?: scalar|null,
 *         cookie_secure?: true|false|"auto", // Default: "auto"
 *         cookie_httponly?: bool, // Default: true
 *         cookie_samesite?: null|"lax"|"strict"|"none", // Default: "lax"
 *         use_cookies?: bool,
 *         gc_divisor?: scalar|null,
 *         gc_probability?: scalar|null,
 *         gc_maxlifetime?: scalar|null,
 *         save_path?: scalar|null, // Defaults to "%kernel.cache_dir%/sessions" if the "handler_id" option is not null.
 *         metadata_update_threshold?: int, // Seconds to wait between 2 session metadata updates. // Default: 0
 *         sid_length?: int, // Deprecated: Setting the "framework.session.sid_length.sid_length" configuration option is deprecated. It will be removed in version 8.0. No alternative is provided as PHP 8.4 has deprecated the related option.
 *         sid_bits_per_character?: int, // Deprecated: Setting the "framework.session.sid_bits_per_character.sid_bits_per_character" configuration option is deprecated. It will be removed in version 8.0. No alternative is provided as PHP 8.4 has deprecated the related option.
 *     },
 *     request?: bool|array{ // Request configuration
 *         enabled?: bool, // Default: false
 *         formats?: array<string, string|list<scalar|null>>,
 *     },
 *     assets?: bool|array{ // Assets configuration
 *         enabled?: bool, // Default: true
 *         strict_mode?: bool, // Throw an exception if an entry is missing from the manifest.json. // Default: false
 *         version_strategy?: scalar|null, // Default: null
 *         version?: scalar|null, // Default: null
 *         version_format?: scalar|null, // Default: "%%s?%%s"
 *         json_manifest_path?: scalar|null, // Default: null
 *         base_path?: scalar|null, // Default: ""
 *         base_urls?: list<scalar|null>,
 *         packages?: array<string, array{ // Default: []
 *             strict_mode?: bool, // Throw an exception if an entry is missing from the manifest.json. // Default: false
 *             version_strategy?: scalar|null, // Default: null
 *             version?: scalar|null,
 *             version_format?: scalar|null, // Default: null
 *             json_manifest_path?: scalar|null, // Default: null
 *             base_path?: scalar|null, // Default: ""
 *             base_urls?: list<scalar|null>,
 *         }>,
 *     },
 *     asset_mapper?: bool|array{ // Asset Mapper configuration
 *         enabled?: bool, // Default: false
 *         paths?: array<string, scalar|null>,
 *         excluded_patterns?: list<scalar|null>,
 *         exclude_dotfiles?: bool, // If true, any files starting with "." will be excluded from the asset mapper. // Default: true
 *         server?: bool, // If true, a "dev server" will return the assets from the public directory (true in "debug" mode only by default). // Default: true
 *         public_prefix?: scalar|null, // The public path where the assets will be written to (and served from when "server" is true). // Default: "/assets/"
 *         missing_import_mode?: "strict"|"warn"|"ignore", // Behavior if an asset cannot be found when imported from JavaScript or CSS files - e.g. "import './non-existent.js'". "strict" means an exception is thrown, "warn" means a warning is logged, "ignore" means the import is left as-is. // Default: "warn"
 *         extensions?: array<string, scalar|null>,
 *         importmap_path?: scalar|null, // The path of the importmap.php file. // Default: "%kernel.project_dir%/importmap.php"
 *         importmap_polyfill?: scalar|null, // The importmap name that will be used to load the polyfill. Set to false to disable. // Default: "es-module-shims"
 *         importmap_script_attributes?: array<string, scalar|null>,
 *         vendor_dir?: scalar|null, // The directory to store JavaScript vendors. // Default: "%kernel.project_dir%/assets/vendor"
 *         precompress?: bool|array{ // Precompress assets with Brotli, Zstandard and gzip.
 *             enabled?: bool, // Default: false
 *             formats?: list<scalar|null>,
 *             extensions?: list<scalar|null>,
 *         },
 *     },
 *     translator?: bool|array{ // Translator configuration
 *         enabled?: bool, // Default: true
 *         fallbacks?: list<scalar|null>,
 *         logging?: bool, // Default: false
 *         formatter?: scalar|null, // Default: "translator.formatter.default"
 *         cache_dir?: scalar|null, // Default: "%kernel.cache_dir%/translations"
 *         default_path?: scalar|null, // The default path used to load translations. // Default: "%kernel.project_dir%/translations"
 *         paths?: list<scalar|null>,
 *         pseudo_localization?: bool|array{
 *             enabled?: bool, // Default: false
 *             accents?: bool, // Default: true
 *             expansion_factor?: float, // Default: 1.0
 *             brackets?: bool, // Default: true
 *             parse_html?: bool, // Default: false
 *             localizable_html_attributes?: list<scalar|null>,
 *         },
 *         providers?: array<string, array{ // Default: []
 *             dsn?: scalar|null,
 *             domains?: list<scalar|null>,
 *             locales?: list<scalar|null>,
 *         }>,
 *         globals?: array<string, string|array{ // Default: []
 *             value?: mixed,
 *             message?: string,
 *             parameters?: array<string, scalar|null>,
 *             domain?: string,
 *         }>,
 *     },
 *     validation?: bool|array{ // Validation configuration
 *         enabled?: bool, // Default: true
 *         cache?: scalar|null, // Deprecated: Setting the "framework.validation.cache.cache" configuration option is deprecated. It will be removed in version 8.0.
 *         enable_attributes?: bool, // Default: true
 *         static_method?: list<scalar|null>,
 *         translation_domain?: scalar|null, // Default: "validators"
 *         email_validation_mode?: "html5"|"html5-allow-no-tld"|"strict"|"loose", // Default: "html5"
 *         mapping?: array{
 *             paths?: list<scalar|null>,
 *         },
 *         not_compromised_password?: bool|array{
 *             enabled?: bool, // When disabled, compromised passwords will be accepted as valid. // Default: true
 *             endpoint?: scalar|null, // API endpoint for the NotCompromisedPassword Validator. // Default: null
 *         },
 *         disable_translation?: bool, // Default: false
 *         auto_mapping?: array<string, array{ // Default: []
 *             services?: list<scalar|null>,
 *         }>,
 *     },
 *     annotations?: bool|array{
 *         enabled?: bool, // Default: false
 *     },
 *     serializer?: bool|array{ // Serializer configuration
 *         enabled?: bool, // Default: true
 *         enable_attributes?: bool, // Default: true
 *         name_converter?: scalar|null,
 *         circular_reference_handler?: scalar|null,
 *         max_depth_handler?: scalar|null,
 *         mapping?: array{
 *             paths?: list<scalar|null>,
 *         },
 *         default_context?: list<mixed>,
 *         named_serializers?: array<string, array{ // Default: []
 *             name_converter?: scalar|null,
 *             default_context?: list<mixed>,
 *             include_built_in_normalizers?: bool, // Whether to include the built-in normalizers // Default: true
 *             include_built_in_encoders?: bool, // Whether to include the built-in encoders // Default: true
 *         }>,
 *     },
 *     property_access?: bool|array{ // Property access configuration
 *         enabled?: bool, // Default: true
 *         magic_call?: bool, // Default: false
 *         magic_get?: bool, // Default: true
 *         magic_set?: bool, // Default: true
 *         throw_exception_on_invalid_index?: bool, // Default: false
 *         throw_exception_on_invalid_property_path?: bool, // Default: true
 *     },
 *     type_info?: bool|array{ // Type info configuration
 *         enabled?: bool, // Default: true
 *         aliases?: array<string, scalar|null>,
 *     },
 *     property_info?: bool|array{ // Property info configuration
 *         enabled?: bool, // Default: true
 *         with_constructor_extractor?: bool, // Registers the constructor extractor.
 *     },
 *     cache?: array{ // Cache configuration
 *         prefix_seed?: scalar|null, // Used to namespace cache keys when using several apps with the same shared backend. // Default: "_%kernel.project_dir%.%kernel.container_class%"
 *         app?: scalar|null, // App related cache pools configuration. // Default: "cache.adapter.filesystem"
 *         system?: scalar|null, // System related cache pools configuration. // Default: "cache.adapter.system"
 *         directory?: scalar|null, // Default: "%kernel.share_dir%/pools/app"
 *         default_psr6_provider?: scalar|null,
 *         default_redis_provider?: scalar|null, // Default: "redis://localhost"
 *         default_valkey_provider?: scalar|null, // Default: "valkey://localhost"
 *         default_memcached_provider?: scalar|null, // Default: "memcached://localhost"
 *         default_doctrine_dbal_provider?: scalar|null, // Default: "database_connection"
 *         default_pdo_provider?: scalar|null, // Default: null
 *         pools?: array<string, array{ // Default: []
 *             adapters?: list<scalar|null>,
 *             tags?: scalar|null, // Default: null
 *             public?: bool, // Default: false
 *             default_lifetime?: scalar|null, // Default lifetime of the pool.
 *             provider?: scalar|null, // Overwrite the setting from the default provider for this adapter.
 *             early_expiration_message_bus?: scalar|null,
 *             clearer?: scalar|null,
 *         }>,
 *     },
 *     php_errors?: array{ // PHP errors handling configuration
 *         log?: mixed, // Use the application logger instead of the PHP logger for logging PHP errors. // Default: true
 *         throw?: bool, // Throw PHP errors as \ErrorException instances. // Default: true
 *     },
 *     exceptions?: array<string, array{ // Default: []
 *         log_level?: scalar|null, // The level of log message. Null to let Symfony decide. // Default: null
 *         status_code?: scalar|null, // The status code of the response. Null or 0 to let Symfony decide. // Default: null
 *         log_channel?: scalar|null, // The channel of log message. Null to let Symfony decide. // Default: null
 *     }>,
 *     web_link?: bool|array{ // Web links configuration
 *         enabled?: bool, // Default: true
 *     },
 *     lock?: bool|string|array{ // Lock configuration
 *         enabled?: bool, // Default: false
 *         resources?: array<string, string|list<scalar|null>>,
 *     },
 *     semaphore?: bool|string|array{ // Semaphore configuration
 *         enabled?: bool, // Default: false
 *         resources?: array<string, scalar|null>,
 *     },
 *     messenger?: bool|array{ // Messenger configuration
 *         enabled?: bool, // Default: false
 *         routing?: array<string, array{ // Default: []
 *             senders?: list<scalar|null>,
 *         }>,
 *         serializer?: array{
 *             default_serializer?: scalar|null, // Service id to use as the default serializer for the transports. // Default: "messenger.transport.native_php_serializer"
 *             symfony_serializer?: array{
 *                 format?: scalar|null, // Serialization format for the messenger.transport.symfony_serializer service (which is not the serializer used by default). // Default: "json"
 *                 context?: array<string, mixed>,
 *             },
 *         },
 *         transports?: array<string, string|array{ // Default: []
 *             dsn?: scalar|null,
 *             serializer?: scalar|null, // Service id of a custom serializer to use. // Default: null
 *             options?: list<mixed>,
 *             failure_transport?: scalar|null, // Transport name to send failed messages to (after all retries have failed). // Default: null
 *             retry_strategy?: string|array{
 *                 service?: scalar|null, // Service id to override the retry strategy entirely. // Default: null
 *                 max_retries?: int, // Default: 3
 *                 delay?: int, // Time in ms to delay (or the initial value when multiplier is used). // Default: 1000
 *                 multiplier?: float, // If greater than 1, delay will grow exponentially for each retry: this delay = (delay * (multiple ^ retries)). // Default: 2
 *                 max_delay?: int, // Max time in ms that a retry should ever be delayed (0 = infinite). // Default: 0
 *                 jitter?: float, // Randomness to apply to the delay (between 0 and 1). // Default: 0.1
 *             },
 *             rate_limiter?: scalar|null, // Rate limiter name to use when processing messages. // Default: null
 *         }>,
 *         failure_transport?: scalar|null, // Transport name to send failed messages to (after all retries have failed). // Default: null
 *         stop_worker_on_signals?: list<scalar|null>,
 *         default_bus?: scalar|null, // Default: null
 *         buses?: array<string, array{ // Default: {"messenger.bus.default":{"default_middleware":{"enabled":true,"allow_no_handlers":false,"allow_no_senders":true},"middleware":[]}}
 *             default_middleware?: bool|string|array{
 *                 enabled?: bool, // Default: true
 *                 allow_no_handlers?: bool, // Default: false
 *                 allow_no_senders?: bool, // Default: true
 *             },
 *             middleware?: list<string|array{ // Default: []
 *                 id: scalar|null,
 *                 arguments?: list<mixed>,
 *             }>,
 *         }>,
 *     },
 *     scheduler?: bool|array{ // Scheduler configuration
 *         enabled?: bool, // Default: false
 *     },
 *     disallow_search_engine_index?: bool, // Enabled by default when debug is enabled. // Default: true
 *     http_client?: bool|array{ // HTTP Client configuration
 *         enabled?: bool, // Default: true
 *         max_host_connections?: int, // The maximum number of connections to a single host.
 *         default_options?: array{
 *             headers?: array<string, mixed>,
 *             vars?: list<mixed>,
 *             max_redirects?: int, // The maximum number of redirects to follow.
 *             http_version?: scalar|null, // The default HTTP version, typically 1.1 or 2.0, leave to null for the best version.
 *             resolve?: array<string, scalar|null>,
 *             proxy?: scalar|null, // The URL of the proxy to pass requests through or null for automatic detection.
 *             no_proxy?: scalar|null, // A comma separated list of hosts that do not require a proxy to be reached.
 *             timeout?: float, // The idle timeout, defaults to the "default_socket_timeout" ini parameter.
 *             max_duration?: float, // The maximum execution time for the request+response as a whole.
 *             bindto?: scalar|null, // A network interface name, IP address, a host name or a UNIX socket to bind to.
 *             verify_peer?: bool, // Indicates if the peer should be verified in a TLS context.
 *             verify_host?: bool, // Indicates if the host should exist as a certificate common name.
 *             cafile?: scalar|null, // A certificate authority file.
 *             capath?: scalar|null, // A directory that contains multiple certificate authority files.
 *             local_cert?: scalar|null, // A PEM formatted certificate file.
 *             local_pk?: scalar|null, // A private key file.
 *             passphrase?: scalar|null, // The passphrase used to encrypt the "local_pk" file.
 *             ciphers?: scalar|null, // A list of TLS ciphers separated by colons, commas or spaces (e.g. "RC3-SHA:TLS13-AES-128-GCM-SHA256"...)
 *             peer_fingerprint?: array{ // Associative array: hashing algorithm => hash(es).
 *                 sha1?: mixed,
 *                 pin-sha256?: mixed,
 *                 md5?: mixed,
 *             },
 *             crypto_method?: scalar|null, // The minimum version of TLS to accept; must be one of STREAM_CRYPTO_METHOD_TLSv*_CLIENT constants.
 *             extra?: list<mixed>,
 *             rate_limiter?: scalar|null, // Rate limiter name to use for throttling requests. // Default: null
 *             caching?: bool|array{ // Caching configuration.
 *                 enabled?: bool, // Default: false
 *                 cache_pool?: string, // The taggable cache pool to use for storing the responses. // Default: "cache.http_client"
 *                 shared?: bool, // Indicates whether the cache is shared (public) or private. // Default: true
 *                 max_ttl?: int, // The maximum TTL (in seconds) allowed for cached responses. Null means no cap. // Default: null
 *             },
 *             retry_failed?: bool|array{
 *                 enabled?: bool, // Default: false
 *                 retry_strategy?: scalar|null, // service id to override the retry strategy. // Default: null
 *                 http_codes?: array<string, array{ // Default: []
 *                     code?: int,
 *                     methods?: list<string>,
 *                 }>,
 *                 max_retries?: int, // Default: 3
 *                 delay?: int, // Time in ms to delay (or the initial value when multiplier is used). // Default: 1000
 *                 multiplier?: float, // If greater than 1, delay will grow exponentially for each retry: delay * (multiple ^ retries). // Default: 2
 *                 max_delay?: int, // Max time in ms that a retry should ever be delayed (0 = infinite). // Default: 0
 *                 jitter?: float, // Randomness in percent (between 0 and 1) to apply to the delay. // Default: 0.1
 *             },
 *         },
 *         mock_response_factory?: scalar|null, // The id of the service that should generate mock responses. It should be either an invokable or an iterable.
 *         scoped_clients?: array<string, string|array{ // Default: []
 *             scope?: scalar|null, // The regular expression that the request URL must match before adding the other options. When none is provided, the base URI is used instead.
 *             base_uri?: scalar|null, // The URI to resolve relative URLs, following rules in RFC 3985, section 2.
 *             auth_basic?: scalar|null, // An HTTP Basic authentication "username:password".
 *             auth_bearer?: scalar|null, // A token enabling HTTP Bearer authorization.
 *             auth_ntlm?: scalar|null, // A "username:password" pair to use Microsoft NTLM authentication (requires the cURL extension).
 *             query?: array<string, scalar|null>,
 *             headers?: array<string, mixed>,
 *             max_redirects?: int, // The maximum number of redirects to follow.
 *             http_version?: scalar|null, // The default HTTP version, typically 1.1 or 2.0, leave to null for the best version.
 *             resolve?: array<string, scalar|null>,
 *             proxy?: scalar|null, // The URL of the proxy to pass requests through or null for automatic detection.
 *             no_proxy?: scalar|null, // A comma separated list of hosts that do not require a proxy to be reached.
 *             timeout?: float, // The idle timeout, defaults to the "default_socket_timeout" ini parameter.
 *             max_duration?: float, // The maximum execution time for the request+response as a whole.
 *             bindto?: scalar|null, // A network interface name, IP address, a host name or a UNIX socket to bind to.
 *             verify_peer?: bool, // Indicates if the peer should be verified in a TLS context.
 *             verify_host?: bool, // Indicates if the host should exist as a certificate common name.
 *             cafile?: scalar|null, // A certificate authority file.
 *             capath?: scalar|null, // A directory that contains multiple certificate authority files.
 *             local_cert?: scalar|null, // A PEM formatted certificate file.
 *             local_pk?: scalar|null, // A private key file.
 *             passphrase?: scalar|null, // The passphrase used to encrypt the "local_pk" file.
 *             ciphers?: scalar|null, // A list of TLS ciphers separated by colons, commas or spaces (e.g. "RC3-SHA:TLS13-AES-128-GCM-SHA256"...).
 *             peer_fingerprint?: array{ // Associative array: hashing algorithm => hash(es).
 *                 sha1?: mixed,
 *                 pin-sha256?: mixed,
 *                 md5?: mixed,
 *             },
 *             crypto_method?: scalar|null, // The minimum version of TLS to accept; must be one of STREAM_CRYPTO_METHOD_TLSv*_CLIENT constants.
 *             extra?: list<mixed>,
 *             rate_limiter?: scalar|null, // Rate limiter name to use for throttling requests. // Default: null
 *             caching?: bool|array{ // Caching configuration.
 *                 enabled?: bool, // Default: false
 *                 cache_pool?: string, // The taggable cache pool to use for storing the responses. // Default: "cache.http_client"
 *                 shared?: bool, // Indicates whether the cache is shared (public) or private. // Default: true
 *                 max_ttl?: int, // The maximum TTL (in seconds) allowed for cached responses. Null means no cap. // Default: null
 *             },
 *             retry_failed?: bool|array{
 *                 enabled?: bool, // Default: false
 *                 retry_strategy?: scalar|null, // service id to override the retry strategy. // Default: null
 *                 http_codes?: array<string, array{ // Default: []
 *                     code?: int,
 *                     methods?: list<string>,
 *                 }>,
 *                 max_retries?: int, // Default: 3
 *                 delay?: int, // Time in ms to delay (or the initial value when multiplier is used). // Default: 1000
 *                 multiplier?: float, // If greater than 1, delay will grow exponentially for each retry: delay * (multiple ^ retries). // Default: 2
 *                 max_delay?: int, // Max time in ms that a retry should ever be delayed (0 = infinite). // Default: 0
 *                 jitter?: float, // Randomness in percent (between 0 and 1) to apply to the delay. // Default: 0.1
 *             },
 *         }>,
 *     },
 *     mailer?: bool|array{ // Mailer configuration
 *         enabled?: bool, // Default: true
 *         message_bus?: scalar|null, // The message bus to use. Defaults to the default bus if the Messenger component is installed. // Default: null
 *         dsn?: scalar|null, // Default: null
 *         transports?: array<string, scalar|null>,
 *         envelope?: array{ // Mailer Envelope configuration
 *             sender?: scalar|null,
 *             recipients?: list<scalar|null>,
 *             allowed_recipients?: list<scalar|null>,
 *         },
 *         headers?: array<string, string|array{ // Default: []
 *             value?: mixed,
 *         }>,
 *         dkim_signer?: bool|array{ // DKIM signer configuration
 *             enabled?: bool, // Default: false
 *             key?: scalar|null, // Key content, or path to key (in PEM format with the `file://` prefix) // Default: ""
 *             domain?: scalar|null, // Default: ""
 *             select?: scalar|null, // Default: ""
 *             passphrase?: scalar|null, // The private key passphrase // Default: ""
 *             options?: array<string, mixed>,
 *         },
 *         smime_signer?: bool|array{ // S/MIME signer configuration
 *             enabled?: bool, // Default: false
 *             key?: scalar|null, // Path to key (in PEM format) // Default: ""
 *             certificate?: scalar|null, // Path to certificate (in PEM format without the `file://` prefix) // Default: ""
 *             passphrase?: scalar|null, // The private key passphrase // Default: null
 *             extra_certificates?: scalar|null, // Default: null
 *             sign_options?: int, // Default: null
 *         },
 *         smime_encrypter?: bool|array{ // S/MIME encrypter configuration
 *             enabled?: bool, // Default: false
 *             repository?: scalar|null, // S/MIME certificate repository service. This service shall implement the `Symfony\Component\Mailer\EventListener\SmimeCertificateRepositoryInterface`. // Default: ""
 *             cipher?: int, // A set of algorithms used to encrypt the message // Default: null
 *         },
 *     },
 *     secrets?: bool|array{
 *         enabled?: bool, // Default: true
 *         vault_directory?: scalar|null, // Default: "%kernel.project_dir%/config/secrets/%kernel.runtime_environment%"
 *         local_dotenv_file?: scalar|null, // Default: "%kernel.project_dir%/.env.%kernel.runtime_environment%.local"
 *         decryption_env_var?: scalar|null, // Default: "base64:default::SYMFONY_DECRYPTION_SECRET"
 *     },
 *     notifier?: bool|array{ // Notifier configuration
 *         enabled?: bool, // Default: false
 *         message_bus?: scalar|null, // The message bus to use. Defaults to the default bus if the Messenger component is installed. // Default: null
 *         chatter_transports?: array<string, scalar|null>,
 *         texter_transports?: array<string, scalar|null>,
 *         notification_on_failed_messages?: bool, // Default: false
 *         channel_policy?: array<string, string|list<scalar|null>>,
 *         admin_recipients?: list<array{ // Default: []
 *             email?: scalar|null,
 *             phone?: scalar|null, // Default: ""
 *         }>,
 *     },
 *     rate_limiter?: bool|array{ // Rate limiter configuration
 *         enabled?: bool, // Default: true
 *         limiters?: array<string, array{ // Default: []
 *             lock_factory?: scalar|null, // The service ID of the lock factory used by this limiter (or null to disable locking). // Default: "auto"
 *             cache_pool?: scalar|null, // The cache pool to use for storing the current limiter state. // Default: "cache.rate_limiter"
 *             storage_service?: scalar|null, // The service ID of a custom storage implementation, this precedes any configured "cache_pool". // Default: null
 *             policy: "fixed_window"|"token_bucket"|"sliding_window"|"compound"|"no_limit", // The algorithm to be used by this limiter.
 *             limiters?: list<scalar|null>,
 *             limit?: int, // The maximum allowed hits in a fixed interval or burst.
 *             interval?: scalar|null, // Configures the fixed interval if "policy" is set to "fixed_window" or "sliding_window". The value must be a number followed by "second", "minute", "hour", "day", "week" or "month" (or their plural equivalent).
 *             rate?: array{ // Configures the fill rate if "policy" is set to "token_bucket".
 *                 interval?: scalar|null, // Configures the rate interval. The value must be a number followed by "second", "minute", "hour", "day", "week" or "month" (or their plural equivalent).
 *                 amount?: int, // Amount of tokens to add each interval. // Default: 1
 *             },
 *         }>,
 *     },
 *     uid?: bool|array{ // Uid configuration
 *         enabled?: bool, // Default: true
 *         default_uuid_version?: 7|6|4|1, // Default: 7
 *         name_based_uuid_version?: 5|3, // Default: 5
 *         name_based_uuid_namespace?: scalar|null,
 *         time_based_uuid_version?: 7|6|1, // Default: 7
 *         time_based_uuid_node?: scalar|null,
 *     },
 *     html_sanitizer?: bool|array{ // HtmlSanitizer configuration
 *         enabled?: bool, // Default: false
 *         sanitizers?: array<string, array{ // Default: []
 *             allow_safe_elements?: bool, // Allows "safe" elements and attributes. // Default: false
 *             allow_static_elements?: bool, // Allows all static elements and attributes from the W3C Sanitizer API standard. // Default: false
 *             allow_elements?: array<string, mixed>,
 *             block_elements?: list<string>,
 *             drop_elements?: list<string>,
 *             allow_attributes?: array<string, mixed>,
 *             drop_attributes?: array<string, mixed>,
 *             force_attributes?: array<string, array<string, string>>,
 *             force_https_urls?: bool, // Transforms URLs using the HTTP scheme to use the HTTPS scheme instead. // Default: false
 *             allowed_link_schemes?: list<string>,
 *             allowed_link_hosts?: list<string>|null,
 *             allow_relative_links?: bool, // Allows relative URLs to be used in links href attributes. // Default: false
 *             allowed_media_schemes?: list<string>,
 *             allowed_media_hosts?: list<string>|null,
 *             allow_relative_medias?: bool, // Allows relative URLs to be used in media source attributes (img, audio, video, ...). // Default: false
 *             with_attribute_sanitizers?: list<string>,
 *             without_attribute_sanitizers?: list<string>,
 *             max_input_length?: int, // The maximum length allowed for the sanitized input. // Default: 0
 *         }>,
 *     },
 *     webhook?: bool|array{ // Webhook configuration
 *         enabled?: bool, // Default: false
 *         message_bus?: scalar|null, // The message bus to use. // Default: "messenger.default_bus"
 *         routing?: array<string, array{ // Default: []
 *             service: scalar|null,
 *             secret?: scalar|null, // Default: ""
 *         }>,
 *     },
 *     remote-event?: bool|array{ // RemoteEvent configuration
 *         enabled?: bool, // Default: false
 *     },
 *     json_streamer?: bool|array{ // JSON streamer configuration
 *         enabled?: bool, // Default: false
 *     },
 * }
 * @psalm-type DoctrineConfig = array{
 *     dbal?: array{
 *         default_connection?: scalar|null,
 *         types?: array<string, string|array{ // Default: []
 *             class: scalar|null,
 *             commented?: bool, // Deprecated: The doctrine-bundle type commenting features were removed; the corresponding config parameter was deprecated in 2.0 and will be dropped in 3.0.
 *         }>,
 *         driver_schemes?: array<string, scalar|null>,
 *         connections?: array<string, array{ // Default: []
 *             url?: scalar|null, // A URL with connection information; any parameter value parsed from this string will override explicitly set parameters
 *             dbname?: scalar|null,
 *             host?: scalar|null, // Defaults to "localhost" at runtime.
 *             port?: scalar|null, // Defaults to null at runtime.
 *             user?: scalar|null, // Defaults to "root" at runtime.
 *             password?: scalar|null, // Defaults to null at runtime.
 *             override_url?: bool, // Deprecated: The "doctrine.dbal.override_url" configuration key is deprecated.
 *             dbname_suffix?: scalar|null, // Adds the given suffix to the configured database name, this option has no effects for the SQLite platform
 *             application_name?: scalar|null,
 *             charset?: scalar|null,
 *             path?: scalar|null,
 *             memory?: bool,
 *             unix_socket?: scalar|null, // The unix socket to use for MySQL
 *             persistent?: bool, // True to use as persistent connection for the ibm_db2 driver
 *             protocol?: scalar|null, // The protocol to use for the ibm_db2 driver (default to TCPIP if omitted)
 *             service?: bool, // True to use SERVICE_NAME as connection parameter instead of SID for Oracle
 *             servicename?: scalar|null, // Overrules dbname parameter if given and used as SERVICE_NAME or SID connection parameter for Oracle depending on the service parameter.
 *             sessionMode?: scalar|null, // The session mode to use for the oci8 driver
 *             server?: scalar|null, // The name of a running database server to connect to for SQL Anywhere.
 *             default_dbname?: scalar|null, // Override the default database (postgres) to connect to for PostgreSQL connexion.
 *             sslmode?: scalar|null, // Determines whether or with what priority a SSL TCP/IP connection will be negotiated with the server for PostgreSQL.
 *             sslrootcert?: scalar|null, // The name of a file containing SSL certificate authority (CA) certificate(s). If the file exists, the server's certificate will be verified to be signed by one of these authorities.
 *             sslcert?: scalar|null, // The path to the SSL client certificate file for PostgreSQL.
 *             sslkey?: scalar|null, // The path to the SSL client key file for PostgreSQL.
 *             sslcrl?: scalar|null, // The file name of the SSL certificate revocation list for PostgreSQL.
 *             pooled?: bool, // True to use a pooled server with the oci8/pdo_oracle driver
 *             MultipleActiveResultSets?: bool, // Configuring MultipleActiveResultSets for the pdo_sqlsrv driver
 *             use_savepoints?: bool, // Use savepoints for nested transactions
 *             instancename?: scalar|null, // Optional parameter, complete whether to add the INSTANCE_NAME parameter in the connection. It is generally used to connect to an Oracle RAC server to select the name of a particular instance.
 *             connectstring?: scalar|null, // Complete Easy Connect connection descriptor, see https://docs.oracle.com/database/121/NETAG/naming.htm.When using this option, you will still need to provide the user and password parameters, but the other parameters will no longer be used. Note that when using this parameter, the getHost and getPort methods from Doctrine\DBAL\Connection will no longer function as expected.
 *             driver?: scalar|null, // Default: "pdo_mysql"
 *             platform_service?: scalar|null, // Deprecated: The "platform_service" configuration key is deprecated since doctrine-bundle 2.9. DBAL 4 will not support setting a custom platform via connection params anymore.
 *             auto_commit?: bool,
 *             schema_filter?: scalar|null,
 *             logging?: bool, // Default: true
 *             profiling?: bool, // Default: true
 *             profiling_collect_backtrace?: bool, // Enables collecting backtraces when profiling is enabled // Default: false
 *             profiling_collect_schema_errors?: bool, // Enables collecting schema errors when profiling is enabled // Default: true
 *             disable_type_comments?: bool,
 *             server_version?: scalar|null,
 *             idle_connection_ttl?: int, // Default: 600
 *             driver_class?: scalar|null,
 *             wrapper_class?: scalar|null,
 *             keep_slave?: bool, // Deprecated: The "keep_slave" configuration key is deprecated since doctrine-bundle 2.2. Use the "keep_replica" configuration key instead.
 *             keep_replica?: bool,
 *             options?: array<string, mixed>,
 *             mapping_types?: array<string, scalar|null>,
 *             default_table_options?: array<string, scalar|null>,
 *             schema_manager_factory?: scalar|null, // Default: "doctrine.dbal.default_schema_manager_factory"
 *             result_cache?: scalar|null,
 *             slaves?: array<string, array{ // Default: []
 *                 url?: scalar|null, // A URL with connection information; any parameter value parsed from this string will override explicitly set parameters
 *                 dbname?: scalar|null,
 *                 host?: scalar|null, // Defaults to "localhost" at runtime.
 *                 port?: scalar|null, // Defaults to null at runtime.
 *                 user?: scalar|null, // Defaults to "root" at runtime.
 *                 password?: scalar|null, // Defaults to null at runtime.
 *                 override_url?: bool, // Deprecated: The "doctrine.dbal.override_url" configuration key is deprecated.
 *                 dbname_suffix?: scalar|null, // Adds the given suffix to the configured database name, this option has no effects for the SQLite platform
 *                 application_name?: scalar|null,
 *                 charset?: scalar|null,
 *                 path?: scalar|null,
 *                 memory?: bool,
 *                 unix_socket?: scalar|null, // The unix socket to use for MySQL
 *                 persistent?: bool, // True to use as persistent connection for the ibm_db2 driver
 *                 protocol?: scalar|null, // The protocol to use for the ibm_db2 driver (default to TCPIP if omitted)
 *                 service?: bool, // True to use SERVICE_NAME as connection parameter instead of SID for Oracle
 *                 servicename?: scalar|null, // Overrules dbname parameter if given and used as SERVICE_NAME or SID connection parameter for Oracle depending on the service parameter.
 *                 sessionMode?: scalar|null, // The session mode to use for the oci8 driver
 *                 server?: scalar|null, // The name of a running database server to connect to for SQL Anywhere.
 *                 default_dbname?: scalar|null, // Override the default database (postgres) to connect to for PostgreSQL connexion.
 *                 sslmode?: scalar|null, // Determines whether or with what priority a SSL TCP/IP connection will be negotiated with the server for PostgreSQL.
 *                 sslrootcert?: scalar|null, // The name of a file containing SSL certificate authority (CA) certificate(s). If the file exists, the server's certificate will be verified to be signed by one of these authorities.
 *                 sslcert?: scalar|null, // The path to the SSL client certificate file for PostgreSQL.
 *                 sslkey?: scalar|null, // The path to the SSL client key file for PostgreSQL.
 *                 sslcrl?: scalar|null, // The file name of the SSL certificate revocation list for PostgreSQL.
 *                 pooled?: bool, // True to use a pooled server with the oci8/pdo_oracle driver
 *                 MultipleActiveResultSets?: bool, // Configuring MultipleActiveResultSets for the pdo_sqlsrv driver
 *                 use_savepoints?: bool, // Use savepoints for nested transactions
 *                 instancename?: scalar|null, // Optional parameter, complete whether to add the INSTANCE_NAME parameter in the connection. It is generally used to connect to an Oracle RAC server to select the name of a particular instance.
 *                 connectstring?: scalar|null, // Complete Easy Connect connection descriptor, see https://docs.oracle.com/database/121/NETAG/naming.htm.When using this option, you will still need to provide the user and password parameters, but the other parameters will no longer be used. Note that when using this parameter, the getHost and getPort methods from Doctrine\DBAL\Connection will no longer function as expected.
 *             }>,
 *             replicas?: array<string, array{ // Default: []
 *                 url?: scalar|null, // A URL with connection information; any parameter value parsed from this string will override explicitly set parameters
 *                 dbname?: scalar|null,
 *                 host?: scalar|null, // Defaults to "localhost" at runtime.
 *                 port?: scalar|null, // Defaults to null at runtime.
 *                 user?: scalar|null, // Defaults to "root" at runtime.
 *                 password?: scalar|null, // Defaults to null at runtime.
 *                 override_url?: bool, // Deprecated: The "doctrine.dbal.override_url" configuration key is deprecated.
 *                 dbname_suffix?: scalar|null, // Adds the given suffix to the configured database name, this option has no effects for the SQLite platform
 *                 application_name?: scalar|null,
 *                 charset?: scalar|null,
 *                 path?: scalar|null,
 *                 memory?: bool,
 *                 unix_socket?: scalar|null, // The unix socket to use for MySQL
 *                 persistent?: bool, // True to use as persistent connection for the ibm_db2 driver
 *                 protocol?: scalar|null, // The protocol to use for the ibm_db2 driver (default to TCPIP if omitted)
 *                 service?: bool, // True to use SERVICE_NAME as connection parameter instead of SID for Oracle
 *                 servicename?: scalar|null, // Overrules dbname parameter if given and used as SERVICE_NAME or SID connection parameter for Oracle depending on the service parameter.
 *                 sessionMode?: scalar|null, // The session mode to use for the oci8 driver
 *                 server?: scalar|null, // The name of a running database server to connect to for SQL Anywhere.
 *                 default_dbname?: scalar|null, // Override the default database (postgres) to connect to for PostgreSQL connexion.
 *                 sslmode?: scalar|null, // Determines whether or with what priority a SSL TCP/IP connection will be negotiated with the server for PostgreSQL.
 *                 sslrootcert?: scalar|null, // The name of a file containing SSL certificate authority (CA) certificate(s). If the file exists, the server's certificate will be verified to be signed by one of these authorities.
 *                 sslcert?: scalar|null, // The path to the SSL client certificate file for PostgreSQL.
 *                 sslkey?: scalar|null, // The path to the SSL client key file for PostgreSQL.
 *                 sslcrl?: scalar|null, // The file name of the SSL certificate revocation list for PostgreSQL.
 *                 pooled?: bool, // True to use a pooled server with the oci8/pdo_oracle driver
 *                 MultipleActiveResultSets?: bool, // Configuring MultipleActiveResultSets for the pdo_sqlsrv driver
 *                 use_savepoints?: bool, // Use savepoints for nested transactions
 *                 instancename?: scalar|null, // Optional parameter, complete whether to add the INSTANCE_NAME parameter in the connection. It is generally used to connect to an Oracle RAC server to select the name of a particular instance.
 *                 connectstring?: scalar|null, // Complete Easy Connect connection descriptor, see https://docs.oracle.com/database/121/NETAG/naming.htm.When using this option, you will still need to provide the user and password parameters, but the other parameters will no longer be used. Note that when using this parameter, the getHost and getPort methods from Doctrine\DBAL\Connection will no longer function as expected.
 *             }>,
 *         }>,
 *     },
 *     orm?: array{
 *         default_entity_manager?: scalar|null,
 *         auto_generate_proxy_classes?: scalar|null, // Auto generate mode possible values are: "NEVER", "ALWAYS", "FILE_NOT_EXISTS", "EVAL", "FILE_NOT_EXISTS_OR_CHANGED", this option is ignored when the "enable_native_lazy_objects" option is true // Default: false
 *         enable_lazy_ghost_objects?: bool, // Enables the new implementation of proxies based on lazy ghosts instead of using the legacy implementation // Default: true
 *         enable_native_lazy_objects?: bool, // Enables the new native implementation of PHP lazy objects instead of generated proxies // Default: false
 *         proxy_dir?: scalar|null, // Configures the path where generated proxy classes are saved when using non-native lazy objects, this option is ignored when the "enable_native_lazy_objects" option is true // Default: "%kernel.build_dir%/doctrine/orm/Proxies"
 *         proxy_namespace?: scalar|null, // Defines the root namespace for generated proxy classes when using non-native lazy objects, this option is ignored when the "enable_native_lazy_objects" option is true // Default: "Proxies"
 *         controller_resolver?: bool|array{
 *             enabled?: bool, // Default: true
 *             auto_mapping?: bool|null, // Set to false to disable using route placeholders as lookup criteria when the primary key doesn't match the argument name // Default: null
 *             evict_cache?: bool, // Set to true to fetch the entity from the database instead of using the cache, if any // Default: false
 *         },
 *         entity_managers?: array<string, array{ // Default: []
 *             query_cache_driver?: string|array{
 *                 type?: scalar|null, // Default: null
 *                 id?: scalar|null,
 *                 pool?: scalar|null,
 *             },
 *             metadata_cache_driver?: string|array{
 *                 type?: scalar|null, // Default: null
 *                 id?: scalar|null,
 *                 pool?: scalar|null,
 *             },
 *             result_cache_driver?: string|array{
 *                 type?: scalar|null, // Default: null
 *                 id?: scalar|null,
 *                 pool?: scalar|null,
 *             },
 *             entity_listeners?: array{
 *                 entities?: array<string, array{ // Default: []
 *                     listeners?: array<string, array{ // Default: []
 *                         events?: list<array{ // Default: []
 *                             type?: scalar|null,
 *                             method?: scalar|null, // Default: null
 *                         }>,
 *                     }>,
 *                 }>,
 *             },
 *             connection?: scalar|null,
 *             class_metadata_factory_name?: scalar|null, // Default: "Doctrine\\ORM\\Mapping\\ClassMetadataFactory"
 *             default_repository_class?: scalar|null, // Default: "Doctrine\\ORM\\EntityRepository"
 *             auto_mapping?: scalar|null, // Default: false
 *             naming_strategy?: scalar|null, // Default: "doctrine.orm.naming_strategy.default"
 *             quote_strategy?: scalar|null, // Default: "doctrine.orm.quote_strategy.default"
 *             typed_field_mapper?: scalar|null, // Default: "doctrine.orm.typed_field_mapper.default"
 *             entity_listener_resolver?: scalar|null, // Default: null
 *             fetch_mode_subselect_batch_size?: scalar|null,
 *             repository_factory?: scalar|null, // Default: "doctrine.orm.container_repository_factory"
 *             schema_ignore_classes?: list<scalar|null>,
 *             report_fields_where_declared?: bool, // Set to "true" to opt-in to the new mapping driver mode that was added in Doctrine ORM 2.16 and will be mandatory in ORM 3.0. See https://github.com/doctrine/orm/pull/10455. // Default: true
 *             validate_xml_mapping?: bool, // Set to "true" to opt-in to the new mapping driver mode that was added in Doctrine ORM 2.14. See https://github.com/doctrine/orm/pull/6728. // Default: false
 *             second_level_cache?: array{
 *                 region_cache_driver?: string|array{
 *                     type?: scalar|null, // Default: null
 *                     id?: scalar|null,
 *                     pool?: scalar|null,
 *                 },
 *                 region_lock_lifetime?: scalar|null, // Default: 60
 *                 log_enabled?: bool, // Default: true
 *                 region_lifetime?: scalar|null, // Default: 3600
 *                 enabled?: bool, // Default: true
 *                 factory?: scalar|null,
 *                 regions?: array<string, array{ // Default: []
 *                     cache_driver?: string|array{
 *                         type?: scalar|null, // Default: null
 *                         id?: scalar|null,
 *                         pool?: scalar|null,
 *                     },
 *                     lock_path?: scalar|null, // Default: "%kernel.cache_dir%/doctrine/orm/slc/filelock"
 *                     lock_lifetime?: scalar|null, // Default: 60
 *                     type?: scalar|null, // Default: "default"
 *                     lifetime?: scalar|null, // Default: 0
 *                     service?: scalar|null,
 *                     name?: scalar|null,
 *                 }>,
 *                 loggers?: array<string, array{ // Default: []
 *                     name?: scalar|null,
 *                     service?: scalar|null,
 *                 }>,
 *             },
 *             hydrators?: array<string, scalar|null>,
 *             mappings?: array<string, bool|string|array{ // Default: []
 *                 mapping?: scalar|null, // Default: true
 *                 type?: scalar|null,
 *                 dir?: scalar|null,
 *                 alias?: scalar|null,
 *                 prefix?: scalar|null,
 *                 is_bundle?: bool,
 *             }>,
 *             dql?: array{
 *                 string_functions?: array<string, scalar|null>,
 *                 numeric_functions?: array<string, scalar|null>,
 *                 datetime_functions?: array<string, scalar|null>,
 *             },
 *             filters?: array<string, string|array{ // Default: []
 *                 class: scalar|null,
 *                 enabled?: bool, // Default: false
 *                 parameters?: array<string, mixed>,
 *             }>,
 *             identity_generation_preferences?: array<string, scalar|null>,
 *         }>,
 *         resolve_target_entities?: array<string, scalar|null>,
 *     },
 * }
 * @psalm-type DoctrineMigrationsConfig = array{
 *     enable_service_migrations?: bool, // Whether to enable fetching migrations from the service container. // Default: false
 *     migrations_paths?: array<string, scalar|null>,
 *     services?: array<string, scalar|null>,
 *     factories?: array<string, scalar|null>,
 *     storage?: array{ // Storage to use for migration status metadata.
 *         table_storage?: array{ // The default metadata storage, implemented as a table in the database.
 *             table_name?: scalar|null, // Default: null
 *             version_column_name?: scalar|null, // Default: null
 *             version_column_length?: scalar|null, // Default: null
 *             executed_at_column_name?: scalar|null, // Default: null
 *             execution_time_column_name?: scalar|null, // Default: null
 *         },
 *     },
 *     migrations?: list<scalar|null>,
 *     connection?: scalar|null, // Connection name to use for the migrations database. // Default: null
 *     em?: scalar|null, // Entity manager name to use for the migrations database (available when doctrine/orm is installed). // Default: null
 *     all_or_nothing?: scalar|null, // Run all migrations in a transaction. // Default: false
 *     check_database_platform?: scalar|null, // Adds an extra check in the generated migrations to allow execution only on the same platform as they were initially generated on. // Default: true
 *     custom_template?: scalar|null, // Custom template path for generated migration classes. // Default: null
 *     organize_migrations?: scalar|null, // Organize migrations mode. Possible values are: "BY_YEAR", "BY_YEAR_AND_MONTH", false // Default: false
 *     enable_profiler?: bool, // Whether or not to enable the profiler collector to calculate and visualize migration status. This adds some queries overhead. // Default: false
 *     transactional?: bool, // Whether or not to wrap migrations in a single transaction. // Default: true
 * }
 * @psalm-type SecurityConfig = array{
 *     access_denied_url?: scalar|null, // Default: null
 *     session_fixation_strategy?: "none"|"migrate"|"invalidate", // Default: "migrate"
 *     hide_user_not_found?: bool, // Deprecated: The "hide_user_not_found" option is deprecated and will be removed in 8.0. Use the "expose_security_errors" option instead.
 *     expose_security_errors?: \Symfony\Component\Security\Http\Authentication\ExposeSecurityLevel::None|\Symfony\Component\Security\Http\Authentication\ExposeSecurityLevel::AccountStatus|\Symfony\Component\Security\Http\Authentication\ExposeSecurityLevel::All, // Default: "none"
 *     erase_credentials?: bool, // Default: true
 *     access_decision_manager?: array{
 *         strategy?: "affirmative"|"consensus"|"unanimous"|"priority",
 *         service?: scalar|null,
 *         strategy_service?: scalar|null,
 *         allow_if_all_abstain?: bool, // Default: false
 *         allow_if_equal_granted_denied?: bool, // Default: true
 *     },
 *     password_hashers?: array<string, string|array{ // Default: []
 *         algorithm?: scalar|null,
 *         migrate_from?: list<scalar|null>,
 *         hash_algorithm?: scalar|null, // Name of hashing algorithm for PBKDF2 (i.e. sha256, sha512, etc..) See hash_algos() for a list of supported algorithms. // Default: "sha512"
 *         key_length?: scalar|null, // Default: 40
 *         ignore_case?: bool, // Default: false
 *         encode_as_base64?: bool, // Default: true
 *         iterations?: scalar|null, // Default: 5000
 *         cost?: int, // Default: null
 *         memory_cost?: scalar|null, // Default: null
 *         time_cost?: scalar|null, // Default: null
 *         id?: scalar|null,
 *     }>,
 *     providers?: array<string, array{ // Default: []
 *         id?: scalar|null,
 *         chain?: array{
 *             providers?: list<scalar|null>,
 *         },
 *         entity?: array{
 *             class: scalar|null, // The full entity class name of your user class.
 *             property?: scalar|null, // Default: null
 *             manager_name?: scalar|null, // Default: null
 *         },
 *         memory?: array{
 *             users?: array<string, array{ // Default: []
 *                 password?: scalar|null, // Default: null
 *                 roles?: list<scalar|null>,
 *             }>,
 *         },
 *         ldap?: array{
 *             service: scalar|null,
 *             base_dn: scalar|null,
 *             search_dn?: scalar|null, // Default: null
 *             search_password?: scalar|null, // Default: null
 *             extra_fields?: list<scalar|null>,
 *             default_roles?: list<scalar|null>,
 *             role_fetcher?: scalar|null, // Default: null
 *             uid_key?: scalar|null, // Default: "sAMAccountName"
 *             filter?: scalar|null, // Default: "({uid_key}={user_identifier})"
 *             password_attribute?: scalar|null, // Default: null
 *         },
 *         saml?: array{
 *             user_class: scalar|null,
 *             default_roles?: list<scalar|null>,
 *         },
 *     }>,
 *     firewalls: array<string, array{ // Default: []
 *         pattern?: scalar|null,
 *         host?: scalar|null,
 *         methods?: list<scalar|null>,
 *         security?: bool, // Default: true
 *         user_checker?: scalar|null, // The UserChecker to use when authenticating users in this firewall. // Default: "security.user_checker"
 *         request_matcher?: scalar|null,
 *         access_denied_url?: scalar|null,
 *         access_denied_handler?: scalar|null,
 *         entry_point?: scalar|null, // An enabled authenticator name or a service id that implements "Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface".
 *         provider?: scalar|null,
 *         stateless?: bool, // Default: false
 *         lazy?: bool, // Default: false
 *         context?: scalar|null,
 *         logout?: array{
 *             enable_csrf?: bool|null, // Default: null
 *             csrf_token_id?: scalar|null, // Default: "logout"
 *             csrf_parameter?: scalar|null, // Default: "_csrf_token"
 *             csrf_token_manager?: scalar|null,
 *             path?: scalar|null, // Default: "/logout"
 *             target?: scalar|null, // Default: "/"
 *             invalidate_session?: bool, // Default: true
 *             clear_site_data?: list<"*"|"cache"|"cookies"|"storage"|"executionContexts">,
 *             delete_cookies?: array<string, array{ // Default: []
 *                 path?: scalar|null, // Default: null
 *                 domain?: scalar|null, // Default: null
 *                 secure?: scalar|null, // Default: false
 *                 samesite?: scalar|null, // Default: null
 *                 partitioned?: scalar|null, // Default: false
 *             }>,
 *         },
 *         switch_user?: array{
 *             provider?: scalar|null,
 *             parameter?: scalar|null, // Default: "_switch_user"
 *             role?: scalar|null, // Default: "ROLE_ALLOWED_TO_SWITCH"
 *             target_route?: scalar|null, // Default: null
 *         },
 *         required_badges?: list<scalar|null>,
 *         custom_authenticators?: list<scalar|null>,
 *         login_throttling?: array{
 *             limiter?: scalar|null, // A service id implementing "Symfony\Component\HttpFoundation\RateLimiter\RequestRateLimiterInterface".
 *             max_attempts?: int, // Default: 5
 *             interval?: scalar|null, // Default: "1 minute"
 *             lock_factory?: scalar|null, // The service ID of the lock factory used by the login rate limiter (or null to disable locking). // Default: null
 *             cache_pool?: string, // The cache pool to use for storing the limiter state // Default: "cache.rate_limiter"
 *             storage_service?: string, // The service ID of a custom storage implementation, this precedes any configured "cache_pool" // Default: null
 *         },
 *         two_factor?: array{
 *             check_path?: scalar|null, // Default: "/2fa_check"
 *             post_only?: bool, // Default: true
 *             auth_form_path?: scalar|null, // Default: "/2fa"
 *             always_use_default_target_path?: bool, // Default: false
 *             default_target_path?: scalar|null, // Default: "/"
 *             success_handler?: scalar|null, // Default: null
 *             failure_handler?: scalar|null, // Default: null
 *             authentication_required_handler?: scalar|null, // Default: null
 *             auth_code_parameter_name?: scalar|null, // Default: "_auth_code"
 *             trusted_parameter_name?: scalar|null, // Default: "_trusted"
 *             remember_me_sets_trusted?: scalar|null, // Default: false
 *             multi_factor?: bool, // Default: false
 *             prepare_on_login?: bool, // Default: false
 *             prepare_on_access_denied?: bool, // Default: false
 *             enable_csrf?: scalar|null, // Default: false
 *             csrf_parameter?: scalar|null, // Default: "_csrf_token"
 *             csrf_token_id?: scalar|null, // Default: "two_factor"
 *             csrf_header?: scalar|null, // Default: null
 *             csrf_token_manager?: scalar|null, // Default: "scheb_two_factor.csrf_token_manager"
 *             provider?: scalar|null, // Default: null
 *         },
 *         webauthn?: array{
 *             user_provider?: scalar|null, // Default: null
 *             options_storage?: scalar|null, // Deprecated: The child node "options_storage" at path "security.firewalls..webauthn.options_storage" is deprecated. Please use the root option "options_storage" instead. // Default: null
 *             success_handler?: scalar|null, // Default: "Webauthn\\Bundle\\Security\\Handler\\DefaultSuccessHandler"
 *             failure_handler?: scalar|null, // Default: "Webauthn\\Bundle\\Security\\Handler\\DefaultFailureHandler"
 *             secured_rp_ids?: array<string, scalar|null>,
 *             authentication?: bool|array{
 *                 enabled?: bool, // Default: true
 *                 profile?: scalar|null, // Default: "default"
 *                 options_builder?: scalar|null, // Default: null
 *                 routes?: array{
 *                     host?: scalar|null, // Default: null
 *                     options_method?: scalar|null, // Default: "POST"
 *                     options_path?: scalar|null, // Default: "/login/options"
 *                     result_method?: scalar|null, // Default: "POST"
 *                     result_path?: scalar|null, // Default: "/login"
 *                 },
 *                 options_handler?: scalar|null, // Default: "Webauthn\\Bundle\\Security\\Handler\\DefaultRequestOptionsHandler"
 *             },
 *             registration?: bool|array{
 *                 enabled?: bool, // Default: false
 *                 profile?: scalar|null, // Default: "default"
 *                 options_builder?: scalar|null, // Default: null
 *                 routes?: array{
 *                     host?: scalar|null, // Default: null
 *                     options_method?: scalar|null, // Default: "POST"
 *                     options_path?: scalar|null, // Default: "/register/options"
 *                     result_method?: scalar|null, // Default: "POST"
 *                     result_path?: scalar|null, // Default: "/register"
 *                 },
 *                 options_handler?: scalar|null, // Default: "Webauthn\\Bundle\\Security\\Handler\\DefaultCreationOptionsHandler"
 *             },
 *         },
 *         x509?: array{
 *             provider?: scalar|null,
 *             user?: scalar|null, // Default: "SSL_CLIENT_S_DN_Email"
 *             credentials?: scalar|null, // Default: "SSL_CLIENT_S_DN"
 *             user_identifier?: scalar|null, // Default: "emailAddress"
 *         },
 *         remote_user?: array{
 *             provider?: scalar|null,
 *             user?: scalar|null, // Default: "REMOTE_USER"
 *         },
 *         saml?: array{
 *             provider?: scalar|null,
 *             remember_me?: bool, // Default: true
 *             success_handler?: scalar|null, // Default: "Nbgrp\\OneloginSamlBundle\\Security\\Http\\Authentication\\SamlAuthenticationSuccessHandler"
 *             failure_handler?: scalar|null,
 *             check_path?: scalar|null, // Default: "/login_check"
 *             use_forward?: bool, // Default: false
 *             login_path?: scalar|null, // Default: "/login"
 *             identifier_attribute?: scalar|null, // Default: null
 *             use_attribute_friendly_name?: bool, // Default: false
 *             user_factory?: scalar|null, // Default: null
 *             token_factory?: scalar|null, // Default: null
 *             persist_user?: bool, // Default: false
 *             always_use_default_target_path?: bool, // Default: false
 *             default_target_path?: scalar|null, // Default: "/"
 *             target_path_parameter?: scalar|null, // Default: "_target_path"
 *             use_referer?: bool, // Default: false
 *             failure_path?: scalar|null, // Default: null
 *             failure_forward?: bool, // Default: false
 *             failure_path_parameter?: scalar|null, // Default: "_failure_path"
 *         },
 *         login_link?: array{
 *             check_route: scalar|null, // Route that will validate the login link - e.g. "app_login_link_verify".
 *             check_post_only?: scalar|null, // If true, only HTTP POST requests to "check_route" will be handled by the authenticator. // Default: false
 *             signature_properties: list<scalar|null>,
 *             lifetime?: int, // The lifetime of the login link in seconds. // Default: 600
 *             max_uses?: int, // Max number of times a login link can be used - null means unlimited within lifetime. // Default: null
 *             used_link_cache?: scalar|null, // Cache service id used to expired links of max_uses is set.
 *             success_handler?: scalar|null, // A service id that implements Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface.
 *             failure_handler?: scalar|null, // A service id that implements Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface.
 *             provider?: scalar|null, // The user provider to load users from.
 *             secret?: scalar|null, // Default: "%kernel.secret%"
 *             always_use_default_target_path?: bool, // Default: false
 *             default_target_path?: scalar|null, // Default: "/"
 *             login_path?: scalar|null, // Default: "/login"
 *             target_path_parameter?: scalar|null, // Default: "_target_path"
 *             use_referer?: bool, // Default: false
 *             failure_path?: scalar|null, // Default: null
 *             failure_forward?: bool, // Default: false
 *             failure_path_parameter?: scalar|null, // Default: "_failure_path"
 *         },
 *         form_login?: array{
 *             provider?: scalar|null,
 *             remember_me?: bool, // Default: true
 *             success_handler?: scalar|null,
 *             failure_handler?: scalar|null,
 *             check_path?: scalar|null, // Default: "/login_check"
 *             use_forward?: bool, // Default: false
 *             login_path?: scalar|null, // Default: "/login"
 *             username_parameter?: scalar|null, // Default: "_username"
 *             password_parameter?: scalar|null, // Default: "_password"
 *             csrf_parameter?: scalar|null, // Default: "_csrf_token"
 *             csrf_token_id?: scalar|null, // Default: "authenticate"
 *             enable_csrf?: bool, // Default: false
 *             post_only?: bool, // Default: true
 *             form_only?: bool, // Default: false
 *             always_use_default_target_path?: bool, // Default: false
 *             default_target_path?: scalar|null, // Default: "/"
 *             target_path_parameter?: scalar|null, // Default: "_target_path"
 *             use_referer?: bool, // Default: false
 *             failure_path?: scalar|null, // Default: null
 *             failure_forward?: bool, // Default: false
 *             failure_path_parameter?: scalar|null, // Default: "_failure_path"
 *         },
 *         form_login_ldap?: array{
 *             provider?: scalar|null,
 *             remember_me?: bool, // Default: true
 *             success_handler?: scalar|null,
 *             failure_handler?: scalar|null,
 *             check_path?: scalar|null, // Default: "/login_check"
 *             use_forward?: bool, // Default: false
 *             login_path?: scalar|null, // Default: "/login"
 *             username_parameter?: scalar|null, // Default: "_username"
 *             password_parameter?: scalar|null, // Default: "_password"
 *             csrf_parameter?: scalar|null, // Default: "_csrf_token"
 *             csrf_token_id?: scalar|null, // Default: "authenticate"
 *             enable_csrf?: bool, // Default: false
 *             post_only?: bool, // Default: true
 *             form_only?: bool, // Default: false
 *             always_use_default_target_path?: bool, // Default: false
 *             default_target_path?: scalar|null, // Default: "/"
 *             target_path_parameter?: scalar|null, // Default: "_target_path"
 *             use_referer?: bool, // Default: false
 *             failure_path?: scalar|null, // Default: null
 *             failure_forward?: bool, // Default: false
 *             failure_path_parameter?: scalar|null, // Default: "_failure_path"
 *             service?: scalar|null, // Default: "ldap"
 *             dn_string?: scalar|null, // Default: "{user_identifier}"
 *             query_string?: scalar|null,
 *             search_dn?: scalar|null, // Default: ""
 *             search_password?: scalar|null, // Default: ""
 *         },
 *         json_login?: array{
 *             provider?: scalar|null,
 *             remember_me?: bool, // Default: true
 *             success_handler?: scalar|null,
 *             failure_handler?: scalar|null,
 *             check_path?: scalar|null, // Default: "/login_check"
 *             use_forward?: bool, // Default: false
 *             login_path?: scalar|null, // Default: "/login"
 *             username_path?: scalar|null, // Default: "username"
 *             password_path?: scalar|null, // Default: "password"
 *         },
 *         json_login_ldap?: array{
 *             provider?: scalar|null,
 *             remember_me?: bool, // Default: true
 *             success_handler?: scalar|null,
 *             failure_handler?: scalar|null,
 *             check_path?: scalar|null, // Default: "/login_check"
 *             use_forward?: bool, // Default: false
 *             login_path?: scalar|null, // Default: "/login"
 *             username_path?: scalar|null, // Default: "username"
 *             password_path?: scalar|null, // Default: "password"
 *             service?: scalar|null, // Default: "ldap"
 *             dn_string?: scalar|null, // Default: "{user_identifier}"
 *             query_string?: scalar|null,
 *             search_dn?: scalar|null, // Default: ""
 *             search_password?: scalar|null, // Default: ""
 *         },
 *         access_token?: array{
 *             provider?: scalar|null,
 *             remember_me?: bool, // Default: true
 *             success_handler?: scalar|null,
 *             failure_handler?: scalar|null,
 *             realm?: scalar|null, // Default: null
 *             token_extractors?: list<scalar|null>,
 *             token_handler: string|array{
 *                 id?: scalar|null,
 *                 oidc_user_info?: string|array{
 *                     base_uri: scalar|null, // Base URI of the userinfo endpoint on the OIDC server, or the OIDC server URI to use the discovery (require "discovery" to be configured).
 *                     discovery?: array{ // Enable the OIDC discovery.
 *                         cache?: array{
 *                             id: scalar|null, // Cache service id to use to cache the OIDC discovery configuration.
 *                         },
 *                     },
 *                     claim?: scalar|null, // Claim which contains the user identifier (e.g. sub, email, etc.). // Default: "sub"
 *                     client?: scalar|null, // HttpClient service id to use to call the OIDC server.
 *                 },
 *                 oidc?: array{
 *                     discovery?: array{ // Enable the OIDC discovery.
 *                         base_uri: list<scalar|null>,
 *                         cache?: array{
 *                             id: scalar|null, // Cache service id to use to cache the OIDC discovery configuration.
 *                         },
 *                     },
 *                     claim?: scalar|null, // Claim which contains the user identifier (e.g.: sub, email..). // Default: "sub"
 *                     audience: scalar|null, // Audience set in the token, for validation purpose.
 *                     issuers: list<scalar|null>,
 *                     algorithm?: array<mixed>,
 *                     algorithms: list<scalar|null>,
 *                     key?: scalar|null, // Deprecated: The "key" option is deprecated and will be removed in 8.0. Use the "keyset" option instead. // JSON-encoded JWK used to sign the token (must contain a "kty" key).
 *                     keyset?: scalar|null, // JSON-encoded JWKSet used to sign the token (must contain a list of valid public keys).
 *                     encryption?: bool|array{
 *                         enabled?: bool, // Default: false
 *                         enforce?: bool, // When enabled, the token shall be encrypted. // Default: false
 *                         algorithms: list<scalar|null>,
 *                         keyset: scalar|null, // JSON-encoded JWKSet used to decrypt the token (must contain a list of valid private keys).
 *                     },
 *                 },
 *                 cas?: array{
 *                     validation_url: scalar|null, // CAS server validation URL
 *                     prefix?: scalar|null, // CAS prefix // Default: "cas"
 *                     http_client?: scalar|null, // HTTP Client service // Default: null
 *                 },
 *                 oauth2?: scalar|null,
 *             },
 *         },
 *         http_basic?: array{
 *             provider?: scalar|null,
 *             realm?: scalar|null, // Default: "Secured Area"
 *         },
 *         http_basic_ldap?: array{
 *             provider?: scalar|null,
 *             realm?: scalar|null, // Default: "Secured Area"
 *             service?: scalar|null, // Default: "ldap"
 *             dn_string?: scalar|null, // Default: "{user_identifier}"
 *             query_string?: scalar|null,
 *             search_dn?: scalar|null, // Default: ""
 *             search_password?: scalar|null, // Default: ""
 *         },
 *         remember_me?: array{
 *             secret?: scalar|null, // Default: "%kernel.secret%"
 *             service?: scalar|null,
 *             user_providers?: list<scalar|null>,
 *             catch_exceptions?: bool, // Default: true
 *             signature_properties?: list<scalar|null>,
 *             token_provider?: string|array{
 *                 service?: scalar|null, // The service ID of a custom remember-me token provider.
 *                 doctrine?: bool|array{
 *                     enabled?: bool, // Default: false
 *                     connection?: scalar|null, // Default: null
 *                 },
 *             },
 *             token_verifier?: scalar|null, // The service ID of a custom rememberme token verifier.
 *             name?: scalar|null, // Default: "REMEMBERME"
 *             lifetime?: int, // Default: 31536000
 *             path?: scalar|null, // Default: "/"
 *             domain?: scalar|null, // Default: null
 *             secure?: true|false|"auto", // Default: null
 *             httponly?: bool, // Default: true
 *             samesite?: null|"lax"|"strict"|"none", // Default: "lax"
 *             always_remember_me?: bool, // Default: false
 *             remember_me_parameter?: scalar|null, // Default: "_remember_me"
 *         },
 *     }>,
 *     access_control?: list<array{ // Default: []
 *         request_matcher?: scalar|null, // Default: null
 *         requires_channel?: scalar|null, // Default: null
 *         path?: scalar|null, // Use the urldecoded format. // Default: null
 *         host?: scalar|null, // Default: null
 *         port?: int, // Default: null
 *         ips?: list<scalar|null>,
 *         attributes?: array<string, scalar|null>,
 *         route?: scalar|null, // Default: null
 *         methods?: list<scalar|null>,
 *         allow_if?: scalar|null, // Default: null
 *         roles?: list<scalar|null>,
 *     }>,
 *     role_hierarchy?: array<string, string|list<scalar|null>>,
 * }
 * @psalm-type TwigConfig = array{
 *     form_themes?: list<scalar|null>,
 *     globals?: array<string, array{ // Default: []
 *         id?: scalar|null,
 *         type?: scalar|null,
 *         value?: mixed,
 *     }>,
 *     autoescape_service?: scalar|null, // Default: null
 *     autoescape_service_method?: scalar|null, // Default: null
 *     base_template_class?: scalar|null, // Deprecated: The child node "base_template_class" at path "twig.base_template_class" is deprecated.
 *     cache?: scalar|null, // Default: true
 *     charset?: scalar|null, // Default: "%kernel.charset%"
 *     debug?: bool, // Default: "%kernel.debug%"
 *     strict_variables?: bool, // Default: "%kernel.debug%"
 *     auto_reload?: scalar|null,
 *     optimizations?: int,
 *     default_path?: scalar|null, // The default path used to load templates. // Default: "%kernel.project_dir%/templates"
 *     file_name_pattern?: list<scalar|null>,
 *     paths?: array<string, mixed>,
 *     date?: array{ // The default format options used by the date filter.
 *         format?: scalar|null, // Default: "F j, Y H:i"
 *         interval_format?: scalar|null, // Default: "%d days"
 *         timezone?: scalar|null, // The timezone used when formatting dates, when set to null, the timezone returned by date_default_timezone_get() is used. // Default: null
 *     },
 *     number_format?: array{ // The default format options for the number_format filter.
 *         decimals?: int, // Default: 0
 *         decimal_point?: scalar|null, // Default: "."
 *         thousands_separator?: scalar|null, // Default: ","
 *     },
 *     mailer?: array{
 *         html_to_text_converter?: scalar|null, // A service implementing the "Symfony\Component\Mime\HtmlToTextConverter\HtmlToTextConverterInterface". // Default: null
 *     },
 * }
 * @psalm-type WebProfilerConfig = array{
 *     toolbar?: bool|array{ // Profiler toolbar configuration
 *         enabled?: bool, // Default: false
 *         ajax_replace?: bool, // Replace toolbar on AJAX requests // Default: false
 *     },
 *     intercept_redirects?: bool, // Default: false
 *     excluded_ajax_paths?: scalar|null, // Default: "^/((index|app(_[\\w]+)?)\\.php/)?_wdt"
 * }
 * @psalm-type MonologConfig = array{
 *     use_microseconds?: scalar|null, // Default: true
 *     channels?: list<scalar|null>,
 *     handlers?: array<string, array{ // Default: []
 *         type: scalar|null,
 *         id?: scalar|null,
 *         enabled?: bool, // Default: true
 *         priority?: scalar|null, // Default: 0
 *         level?: scalar|null, // Default: "DEBUG"
 *         bubble?: bool, // Default: true
 *         interactive_only?: bool, // Default: false
 *         app_name?: scalar|null, // Default: null
 *         fill_extra_context?: bool, // Default: false
 *         include_stacktraces?: bool, // Default: false
 *         process_psr_3_messages?: array{
 *             enabled?: bool|null, // Default: null
 *             date_format?: scalar|null,
 *             remove_used_context_fields?: bool,
 *         },
 *         path?: scalar|null, // Default: "%kernel.logs_dir%/%kernel.environment%.log"
 *         file_permission?: scalar|null, // Default: null
 *         use_locking?: bool, // Default: false
 *         filename_format?: scalar|null, // Default: "{filename}-{date}"
 *         date_format?: scalar|null, // Default: "Y-m-d"
 *         ident?: scalar|null, // Default: false
 *         logopts?: scalar|null, // Default: 1
 *         facility?: scalar|null, // Default: "user"
 *         max_files?: scalar|null, // Default: 0
 *         action_level?: scalar|null, // Default: "WARNING"
 *         activation_strategy?: scalar|null, // Default: null
 *         stop_buffering?: bool, // Default: true
 *         passthru_level?: scalar|null, // Default: null
 *         excluded_404s?: list<scalar|null>,
 *         excluded_http_codes?: list<array{ // Default: []
 *             code?: scalar|null,
 *             urls?: list<scalar|null>,
 *         }>,
 *         accepted_levels?: list<scalar|null>,
 *         min_level?: scalar|null, // Default: "DEBUG"
 *         max_level?: scalar|null, // Default: "EMERGENCY"
 *         buffer_size?: scalar|null, // Default: 0
 *         flush_on_overflow?: bool, // Default: false
 *         handler?: scalar|null,
 *         url?: scalar|null,
 *         exchange?: scalar|null,
 *         exchange_name?: scalar|null, // Default: "log"
 *         room?: scalar|null,
 *         message_format?: scalar|null, // Default: "text"
 *         api_version?: scalar|null, // Default: null
 *         channel?: scalar|null, // Default: null
 *         bot_name?: scalar|null, // Default: "Monolog"
 *         use_attachment?: scalar|null, // Default: true
 *         use_short_attachment?: scalar|null, // Default: false
 *         include_extra?: scalar|null, // Default: false
 *         icon_emoji?: scalar|null, // Default: null
 *         webhook_url?: scalar|null,
 *         exclude_fields?: list<scalar|null>,
 *         team?: scalar|null,
 *         notify?: scalar|null, // Default: false
 *         nickname?: scalar|null, // Default: "Monolog"
 *         token?: scalar|null,
 *         region?: scalar|null,
 *         source?: scalar|null,
 *         use_ssl?: bool, // Default: true
 *         user?: mixed,
 *         title?: scalar|null, // Default: null
 *         host?: scalar|null, // Default: null
 *         port?: scalar|null, // Default: 514
 *         config?: list<scalar|null>,
 *         members?: list<scalar|null>,
 *         connection_string?: scalar|null,
 *         timeout?: scalar|null,
 *         time?: scalar|null, // Default: 60
 *         deduplication_level?: scalar|null, // Default: 400
 *         store?: scalar|null, // Default: null
 *         connection_timeout?: scalar|null,
 *         persistent?: bool,
 *         dsn?: scalar|null,
 *         hub_id?: scalar|null, // Default: null
 *         client_id?: scalar|null, // Default: null
 *         auto_log_stacks?: scalar|null, // Default: false
 *         release?: scalar|null, // Default: null
 *         environment?: scalar|null, // Default: null
 *         message_type?: scalar|null, // Default: 0
 *         parse_mode?: scalar|null, // Default: null
 *         disable_webpage_preview?: bool|null, // Default: null
 *         disable_notification?: bool|null, // Default: null
 *         split_long_messages?: bool, // Default: false
 *         delay_between_messages?: bool, // Default: false
 *         topic?: int, // Default: null
 *         factor?: int, // Default: 1
 *         tags?: list<scalar|null>,
 *         console_formater_options?: mixed, // Deprecated: "monolog.handlers..console_formater_options.console_formater_options" is deprecated, use "monolog.handlers..console_formater_options.console_formatter_options" instead.
 *         console_formatter_options?: mixed, // Default: []
 *         formatter?: scalar|null,
 *         nested?: bool, // Default: false
 *         publisher?: string|array{
 *             id?: scalar|null,
 *             hostname?: scalar|null,
 *             port?: scalar|null, // Default: 12201
 *             chunk_size?: scalar|null, // Default: 1420
 *             encoder?: "json"|"compressed_json",
 *         },
 *         mongo?: string|array{
 *             id?: scalar|null,
 *             host?: scalar|null,
 *             port?: scalar|null, // Default: 27017
 *             user?: scalar|null,
 *             pass?: scalar|null,
 *             database?: scalar|null, // Default: "monolog"
 *             collection?: scalar|null, // Default: "logs"
 *         },
 *         mongodb?: string|array{
 *             id?: scalar|null, // ID of a MongoDB\Client service
 *             uri?: scalar|null,
 *             username?: scalar|null,
 *             password?: scalar|null,
 *             database?: scalar|null, // Default: "monolog"
 *             collection?: scalar|null, // Default: "logs"
 *         },
 *         elasticsearch?: string|array{
 *             id?: scalar|null,
 *             hosts?: list<scalar|null>,
 *             host?: scalar|null,
 *             port?: scalar|null, // Default: 9200
 *             transport?: scalar|null, // Default: "Http"
 *             user?: scalar|null, // Default: null
 *             password?: scalar|null, // Default: null
 *         },
 *         index?: scalar|null, // Default: "monolog"
 *         document_type?: scalar|null, // Default: "logs"
 *         ignore_error?: scalar|null, // Default: false
 *         redis?: string|array{
 *             id?: scalar|null,
 *             host?: scalar|null,
 *             password?: scalar|null, // Default: null
 *             port?: scalar|null, // Default: 6379
 *             database?: scalar|null, // Default: 0
 *             key_name?: scalar|null, // Default: "monolog_redis"
 *         },
 *         predis?: string|array{
 *             id?: scalar|null,
 *             host?: scalar|null,
 *         },
 *         from_email?: scalar|null,
 *         to_email?: list<scalar|null>,
 *         subject?: scalar|null,
 *         content_type?: scalar|null, // Default: null
 *         headers?: list<scalar|null>,
 *         mailer?: scalar|null, // Default: null
 *         email_prototype?: string|array{
 *             id: scalar|null,
 *             method?: scalar|null, // Default: null
 *         },
 *         lazy?: bool, // Default: true
 *         verbosity_levels?: array{
 *             VERBOSITY_QUIET?: scalar|null, // Default: "ERROR"
 *             VERBOSITY_NORMAL?: scalar|null, // Default: "WARNING"
 *             VERBOSITY_VERBOSE?: scalar|null, // Default: "NOTICE"
 *             VERBOSITY_VERY_VERBOSE?: scalar|null, // Default: "INFO"
 *             VERBOSITY_DEBUG?: scalar|null, // Default: "DEBUG"
 *         },
 *         channels?: string|array{
 *             type?: scalar|null,
 *             elements?: list<scalar|null>,
 *         },
 *     }>,
 * }
 * @psalm-type DebugConfig = array{
 *     max_items?: int, // Max number of displayed items past the first level, -1 means no limit. // Default: 2500
 *     min_depth?: int, // Minimum tree depth to clone all the items, 1 is default. // Default: 1
 *     max_string_length?: int, // Max length of displayed strings, -1 means no limit. // Default: -1
 *     dump_destination?: scalar|null, // A stream URL where dumps should be written to. // Default: null
 *     theme?: "dark"|"light", // Changes the color of the dump() output when rendered directly on the templating. "dark" (default) or "light". // Default: "dark"
 * }
 * @psalm-type MakerConfig = array{
 *     root_namespace?: scalar|null, // Default: "App"
 *     generate_final_classes?: bool, // Default: true
 *     generate_final_entities?: bool, // Default: false
 * }
 * @psalm-type WebpackEncoreConfig = array{
 *     output_path: scalar|null, // The path where Encore is building the assets - i.e. Encore.setOutputPath()
 *     crossorigin?: false|"anonymous"|"use-credentials", // crossorigin value when Encore.enableIntegrityHashes() is used, can be false (default), anonymous or use-credentials // Default: false
 *     preload?: bool, // preload all rendered script and link tags automatically via the http2 Link header. // Default: false
 *     cache?: bool, // Enable caching of the entry point file(s) // Default: false
 *     strict_mode?: bool, // Throw an exception if the entrypoints.json file is missing or an entry is missing from the data // Default: true
 *     builds?: array<string, scalar|null>,
 *     script_attributes?: array<string, scalar|null>,
 *     link_attributes?: array<string, scalar|null>,
 * }
 * @psalm-type DatatablesConfig = array{
 *     language_from_cdn?: bool, // Load i18n data from DataTables CDN or locally // Default: true
 *     persist_state?: "none"|"query"|"fragment"|"local"|"session", // Where to persist the current table state automatically // Default: "fragment"
 *     method?: "GET"|"POST", // Default HTTP method to be used for callbacks // Default: "POST"
 *     options?: array<string, mixed>,
 *     renderer?: scalar|null, // Default service used to render templates, built-in TwigRenderer uses global Twig environment // Default: "Omines\\DataTablesBundle\\Twig\\TwigRenderer"
 *     template?: scalar|null, // Default template to be used for DataTables HTML // Default: "@DataTables/datatable_html.html.twig"
 *     template_parameters?: array{ // Default parameters to be passed to the template
 *         className?: scalar|null, // Default class attribute to apply to the root table elements // Default: "table table-bordered"
 *         columnFilter?: "thead"|"tfoot"|"both"|null, // If and where to enable the DataTables Filter module // Default: null
 *         ...<mixed>
 *     },
 *     translation_domain?: scalar|null, // Default translation domain to be used // Default: "messages"
 * }
 * @psalm-type LiipImagineConfig = array{
 *     resolvers?: array<string, array{ // Default: []
 *         web_path?: array{
 *             web_root?: scalar|null, // Default: "%kernel.project_dir%/public"
 *             cache_prefix?: scalar|null, // Default: "media/cache"
 *         },
 *         aws_s3?: array{
 *             bucket: scalar|null,
 *             cache?: scalar|null, // Default: false
 *             use_psr_cache?: bool, // Default: false
 *             acl?: scalar|null, // Default: "public-read"
 *             cache_prefix?: scalar|null, // Default: ""
 *             client_id?: scalar|null, // Default: null
 *             client_config: list<mixed>,
 *             get_options?: array<string, scalar|null>,
 *             put_options?: array<string, scalar|null>,
 *             proxies?: array<string, scalar|null>,
 *         },
 *         flysystem?: array{
 *             filesystem_service: scalar|null,
 *             cache_prefix?: scalar|null, // Default: ""
 *             root_url: scalar|null,
 *             visibility?: "public"|"private"|"noPredefinedVisibility", // Default: "public"
 *         },
 *     }>,
 *     loaders?: array<string, array{ // Default: []
 *         stream?: array{
 *             wrapper: scalar|null,
 *             context?: scalar|null, // Default: null
 *         },
 *         filesystem?: array{
 *             locator?: "filesystem"|"filesystem_insecure", // Using the "filesystem_insecure" locator is not recommended due to a less secure resolver mechanism, but is provided for those using heavily symlinked projects. // Default: "filesystem"
 *             data_root?: list<scalar|null>,
 *             allow_unresolvable_data_roots?: bool, // Default: false
 *             bundle_resources?: array{
 *                 enabled?: bool, // Default: false
 *                 access_control_type?: "blacklist"|"whitelist", // Sets the access control method applied to bundle names in "access_control_list" into a blacklist or whitelist. // Default: "blacklist"
 *                 access_control_list?: list<scalar|null>,
 *             },
 *         },
 *         flysystem?: array{
 *             filesystem_service: scalar|null,
 *         },
 *         chain?: array{
 *             loaders: list<scalar|null>,
 *         },
 *     }>,
 *     driver?: scalar|null, // Default: "gd"
 *     cache?: scalar|null, // Default: "default"
 *     cache_base_path?: scalar|null, // Default: ""
 *     data_loader?: scalar|null, // Default: "default"
 *     default_image?: scalar|null, // Default: null
 *     default_filter_set_settings?: array{
 *         quality?: scalar|null, // Default: 100
 *         jpeg_quality?: scalar|null, // Default: null
 *         png_compression_level?: scalar|null, // Default: null
 *         png_compression_filter?: scalar|null, // Default: null
 *         format?: scalar|null, // Default: null
 *         animated?: bool, // Default: false
 *         cache?: scalar|null, // Default: null
 *         data_loader?: scalar|null, // Default: null
 *         default_image?: scalar|null, // Default: null
 *         filters?: array<string, array<string, mixed>>,
 *         post_processors?: array<string, array<string, mixed>>,
 *     },
 *     controller?: array{
 *         filter_action?: scalar|null, // Default: "Liip\\ImagineBundle\\Controller\\ImagineController::filterAction"
 *         filter_runtime_action?: scalar|null, // Default: "Liip\\ImagineBundle\\Controller\\ImagineController::filterRuntimeAction"
 *         redirect_response_code?: int, // Default: 302
 *     },
 *     filter_sets?: array<string, array{ // Default: []
 *         quality?: scalar|null,
 *         jpeg_quality?: scalar|null,
 *         png_compression_level?: scalar|null,
 *         png_compression_filter?: scalar|null,
 *         format?: scalar|null,
 *         animated?: bool,
 *         cache?: scalar|null,
 *         data_loader?: scalar|null,
 *         default_image?: scalar|null,
 *         filters?: array<string, array<string, mixed>>,
 *         post_processors?: array<string, array<string, mixed>>,
 *     }>,
 *     twig?: array{
 *         mode?: "none"|"lazy"|"legacy", // Twig mode: none/lazy/legacy (default) // Default: "legacy"
 *         assets_version?: scalar|null, // Default: null
 *     },
 *     enqueue?: bool, // Enables integration with enqueue if set true. Allows resolve image caches in background by sending messages to MQ. // Default: false
 *     messenger?: bool|array{ // Enables integration with symfony/messenger if set true. Warmup image caches in background by sending messages to MQ.
 *         enabled?: bool, // Default: false
 *     },
 *     templating?: bool, // Enables integration with symfony/templating component // Default: true
 *     webp?: array{
 *         generate?: bool, // Default: false
 *         quality?: int, // Default: 100
 *         cache?: scalar|null, // Default: null
 *         data_loader?: scalar|null, // Default: null
 *         post_processors?: array<string, array<string, mixed>>,
 *     },
 * }
 * @psalm-type TwigExtraConfig = array{
 *     cache?: bool|array{
 *         enabled?: bool, // Default: false
 *     },
 *     html?: bool|array{
 *         enabled?: bool, // Default: true
 *     },
 *     markdown?: bool|array{
 *         enabled?: bool, // Default: true
 *     },
 *     intl?: bool|array{
 *         enabled?: bool, // Default: true
 *     },
 *     cssinliner?: bool|array{
 *         enabled?: bool, // Default: true
 *     },
 *     inky?: bool|array{
 *         enabled?: bool, // Default: true
 *     },
 *     string?: bool|array{
 *         enabled?: bool, // Default: true
 *     },
 *     commonmark?: array{
 *         renderer?: array{ // Array of options for rendering HTML.
 *             block_separator?: scalar|null,
 *             inner_separator?: scalar|null,
 *             soft_break?: scalar|null,
 *         },
 *         html_input?: "strip"|"allow"|"escape", // How to handle HTML input.
 *         allow_unsafe_links?: bool, // Remove risky link and image URLs by setting this to false. // Default: true
 *         max_nesting_level?: int, // The maximum nesting level for blocks. // Default: 9223372036854775807
 *         max_delimiters_per_line?: int, // The maximum number of strong/emphasis delimiters per line. // Default: 9223372036854775807
 *         slug_normalizer?: array{ // Array of options for configuring how URL-safe slugs are created.
 *             instance?: mixed,
 *             max_length?: int, // Default: 255
 *             unique?: mixed,
 *         },
 *         commonmark?: array{ // Array of options for configuring the CommonMark core extension.
 *             enable_em?: bool, // Default: true
 *             enable_strong?: bool, // Default: true
 *             use_asterisk?: bool, // Default: true
 *             use_underscore?: bool, // Default: true
 *             unordered_list_markers?: list<scalar|null>,
 *         },
 *         ...<mixed>
 *     },
 * }
 * @psalm-type GregwarCaptchaConfig = array{
 *     length?: scalar|null, // Default: 5
 *     width?: scalar|null, // Default: 130
 *     height?: scalar|null, // Default: 50
 *     font?: scalar|null, // Default: "C:\\Users\\mail\\Documents\\PHP\\Part-DB-server\\vendor\\gregwar\\captcha-bundle\\DependencyInjection/../Generator/Font/captcha.ttf"
 *     keep_value?: scalar|null, // Default: false
 *     charset?: scalar|null, // Default: "abcdefhjkmnprstuvwxyz23456789"
 *     as_file?: scalar|null, // Default: false
 *     as_url?: scalar|null, // Default: false
 *     reload?: scalar|null, // Default: false
 *     image_folder?: scalar|null, // Default: "captcha"
 *     web_path?: scalar|null, // Default: "%kernel.project_dir%/public"
 *     gc_freq?: scalar|null, // Default: 100
 *     expiration?: scalar|null, // Default: 60
 *     quality?: scalar|null, // Default: 50
 *     invalid_message?: scalar|null, // Default: "Bad code value"
 *     bypass_code?: scalar|null, // Default: null
 *     whitelist_key?: scalar|null, // Default: "captcha_whitelist_key"
 *     humanity?: scalar|null, // Default: 0
 *     distortion?: scalar|null, // Default: true
 *     max_front_lines?: scalar|null, // Default: null
 *     max_behind_lines?: scalar|null, // Default: null
 *     interpolation?: scalar|null, // Default: true
 *     text_color?: list<scalar|null>,
 *     background_color?: list<scalar|null>,
 *     background_images?: list<scalar|null>,
 *     disabled?: scalar|null, // Default: false
 *     ignore_all_effects?: scalar|null, // Default: false
 *     session_key?: scalar|null, // Default: "captcha"
 * }
 * @psalm-type FlorianvSwapConfig = array{
 *     cache?: array{
 *         ttl?: int, // Default: 3600
 *         type?: scalar|null, // A cache type or service id // Default: null
 *     },
 *     providers?: array{
 *         apilayer_fixer?: array{
 *             priority?: int, // Default: 0
 *             api_key: scalar|null,
 *         },
 *         apilayer_currency_data?: array{
 *             priority?: int, // Default: 0
 *             api_key: scalar|null,
 *         },
 *         apilayer_exchange_rates_data?: array{
 *             priority?: int, // Default: 0
 *             api_key: scalar|null,
 *         },
 *         abstract_api?: array{
 *             priority?: int, // Default: 0
 *             api_key: scalar|null,
 *         },
 *         fixer?: array{
 *             priority?: int, // Default: 0
 *             access_key: scalar|null,
 *             enterprise?: bool, // Default: false
 *         },
 *         cryptonator?: array{
 *             priority?: int, // Default: 0
 *         },
 *         exchange_rates_api?: array{
 *             priority?: int, // Default: 0
 *             access_key: scalar|null,
 *             enterprise?: bool, // Default: false
 *         },
 *         webservicex?: array{
 *             priority?: int, // Default: 0
 *         },
 *         central_bank_of_czech_republic?: array{
 *             priority?: int, // Default: 0
 *         },
 *         central_bank_of_republic_turkey?: array{
 *             priority?: int, // Default: 0
 *         },
 *         european_central_bank?: array{
 *             priority?: int, // Default: 0
 *         },
 *         national_bank_of_romania?: array{
 *             priority?: int, // Default: 0
 *         },
 *         russian_central_bank?: array{
 *             priority?: int, // Default: 0
 *         },
 *         frankfurter?: array{
 *             priority?: int, // Default: 0
 *         },
 *         fawazahmed_currency_api?: array{
 *             priority?: int, // Default: 0
 *         },
 *         bulgarian_national_bank?: array{
 *             priority?: int, // Default: 0
 *         },
 *         national_bank_of_ukraine?: array{
 *             priority?: int, // Default: 0
 *         },
 *         currency_data_feed?: array{
 *             priority?: int, // Default: 0
 *             api_key: scalar|null,
 *         },
 *         currency_layer?: array{
 *             priority?: int, // Default: 0
 *             access_key: scalar|null,
 *             enterprise?: bool, // Default: false
 *         },
 *         forge?: array{
 *             priority?: int, // Default: 0
 *             api_key: scalar|null,
 *         },
 *         open_exchange_rates?: array{
 *             priority?: int, // Default: 0
 *             app_id: scalar|null,
 *             enterprise?: bool, // Default: false
 *         },
 *         xignite?: array{
 *             priority?: int, // Default: 0
 *             token: scalar|null,
 *         },
 *         xchangeapi?: array{
 *             priority?: int, // Default: 0
 *             api_key: scalar|null,
 *         },
 *         currency_converter?: array{
 *             priority?: int, // Default: 0
 *             access_key: scalar|null,
 *             enterprise?: bool, // Default: false
 *         },
 *         array?: array{
 *             priority?: int, // Default: 0
 *             latestRates: mixed,
 *             historicalRates?: mixed,
 *         },
 *     },
 * }
 * @psalm-type NelmioSecurityConfig = array{
 *     signed_cookie?: array{
 *         names?: list<scalar|null>,
 *         secret?: scalar|null, // Default: "%kernel.secret%"
 *         hash_algo?: scalar|null,
 *         legacy_hash_algo?: scalar|null, // Fallback algorithm to allow for frictionless hash algorithm upgrades. Use with caution and as a temporary measure as it allows for downgrade attacks. // Default: null
 *         separator?: scalar|null, // Default: "."
 *     },
 *     clickjacking?: array{
 *         hosts?: list<scalar|null>,
 *         paths?: array<string, array{ // Default: {"^/.*":{"header":"DENY"}}
 *             header?: scalar|null, // Default: "DENY"
 *         }>,
 *         content_types?: list<scalar|null>,
 *     },
 *     external_redirects?: array{
 *         abort?: bool, // Default: false
 *         override?: scalar|null, // Default: null
 *         forward_as?: scalar|null, // Default: null
 *         log?: bool, // Default: false
 *         allow_list?: list<scalar|null>,
 *     },
 *     flexible_ssl?: bool|array{
 *         enabled?: bool, // Default: false
 *         cookie_name?: scalar|null, // Default: "auth"
 *         unsecured_logout?: bool, // Default: false
 *     },
 *     forced_ssl?: bool|array{
 *         enabled?: bool, // Default: false
 *         hsts_max_age?: scalar|null, // Default: null
 *         hsts_subdomains?: bool, // Default: false
 *         hsts_preload?: bool, // Default: false
 *         allow_list?: list<scalar|null>,
 *         hosts?: list<scalar|null>,
 *         redirect_status_code?: scalar|null, // Default: 302
 *     },
 *     content_type?: array{
 *         nosniff?: bool, // Default: false
 *     },
 *     xss_protection?: array{ // Deprecated: The "xss_protection" option is deprecated, use Content Security Policy without allowing "unsafe-inline" scripts instead.
 *         enabled?: bool, // Default: false
 *         mode_block?: bool, // Default: false
 *         report_uri?: scalar|null, // Default: null
 *     },
 *     csp?: bool|array{
 *         enabled?: bool, // Default: true
 *         request_matcher?: scalar|null, // Default: null
 *         hosts?: list<scalar|null>,
 *         content_types?: list<scalar|null>,
 *         report_endpoint?: array{
 *             log_channel?: scalar|null, // Default: null
 *             log_formatter?: scalar|null, // Default: "nelmio_security.csp_report.log_formatter"
 *             log_level?: "alert"|"critical"|"debug"|"emergency"|"error"|"info"|"notice"|"warning", // Default: "notice"
 *             filters?: array{
 *                 domains?: bool, // Default: true
 *                 schemes?: bool, // Default: true
 *                 browser_bugs?: bool, // Default: true
 *                 injected_scripts?: bool, // Default: true
 *             },
 *             dismiss?: list<list<"default-src"|"base-uri"|"block-all-mixed-content"|"child-src"|"connect-src"|"font-src"|"form-action"|"frame-ancestors"|"frame-src"|"img-src"|"manifest-src"|"media-src"|"object-src"|"plugin-types"|"script-src"|"style-src"|"upgrade-insecure-requests"|"report-uri"|"worker-src"|"prefetch-src"|"report-to"|"*">>,
 *         },
 *         compat_headers?: bool, // Default: true
 *         report_logger_service?: scalar|null, // Default: "logger"
 *         hash?: array{
 *             algorithm?: "sha256"|"sha384"|"sha512", // The algorithm to use for hashes // Default: "sha256"
 *         },
 *         report?: array{
 *             level1_fallback?: bool, // Provides CSP Level 1 fallback when using hash or nonce (CSP level 2) by adding 'unsafe-inline' source. See https://www.w3.org/TR/CSP2/#directive-script-src and https://www.w3.org/TR/CSP2/#directive-style-src // Default: true
 *             browser_adaptive?: bool|array{ // Do not send directives that browser do not support
 *                 enabled?: bool, // Default: false
 *                 parser?: scalar|null, // Default: "nelmio_security.ua_parser.ua_php"
 *             },
 *             default-src?: list<scalar|null>,
 *             base-uri?: list<scalar|null>,
 *             block-all-mixed-content?: bool, // Default: false
 *             child-src?: list<scalar|null>,
 *             connect-src?: list<scalar|null>,
 *             font-src?: list<scalar|null>,
 *             form-action?: list<scalar|null>,
 *             frame-ancestors?: list<scalar|null>,
 *             frame-src?: list<scalar|null>,
 *             img-src?: list<scalar|null>,
 *             manifest-src?: list<scalar|null>,
 *             media-src?: list<scalar|null>,
 *             object-src?: list<scalar|null>,
 *             plugin-types?: list<scalar|null>,
 *             script-src?: list<scalar|null>,
 *             style-src?: list<scalar|null>,
 *             upgrade-insecure-requests?: bool, // Default: false
 *             report-uri?: list<scalar|null>,
 *             worker-src?: list<scalar|null>,
 *             prefetch-src?: list<scalar|null>,
 *             report-to?: scalar|null,
 *         },
 *         enforce?: array{
 *             level1_fallback?: bool, // Provides CSP Level 1 fallback when using hash or nonce (CSP level 2) by adding 'unsafe-inline' source. See https://www.w3.org/TR/CSP2/#directive-script-src and https://www.w3.org/TR/CSP2/#directive-style-src // Default: true
 *             browser_adaptive?: bool|array{ // Do not send directives that browser do not support
 *                 enabled?: bool, // Default: false
 *                 parser?: scalar|null, // Default: "nelmio_security.ua_parser.ua_php"
 *             },
 *             default-src?: list<scalar|null>,
 *             base-uri?: list<scalar|null>,
 *             block-all-mixed-content?: bool, // Default: false
 *             child-src?: list<scalar|null>,
 *             connect-src?: list<scalar|null>,
 *             font-src?: list<scalar|null>,
 *             form-action?: list<scalar|null>,
 *             frame-ancestors?: list<scalar|null>,
 *             frame-src?: list<scalar|null>,
 *             img-src?: list<scalar|null>,
 *             manifest-src?: list<scalar|null>,
 *             media-src?: list<scalar|null>,
 *             object-src?: list<scalar|null>,
 *             plugin-types?: list<scalar|null>,
 *             script-src?: list<scalar|null>,
 *             style-src?: list<scalar|null>,
 *             upgrade-insecure-requests?: bool, // Default: false
 *             report-uri?: list<scalar|null>,
 *             worker-src?: list<scalar|null>,
 *             prefetch-src?: list<scalar|null>,
 *             report-to?: scalar|null,
 *         },
 *     },
 *     referrer_policy?: bool|array{
 *         enabled?: bool, // Default: false
 *         policies?: list<scalar|null>,
 *     },
 *     permissions_policy?: bool|array{
 *         enabled?: bool, // Default: false
 *         policies?: array{
 *             accelerometer?: mixed, // Default: null
 *             ambient_light_sensor?: mixed, // Default: null
 *             attribution_reporting?: mixed, // Default: null
 *             autoplay?: mixed, // Default: null
 *             bluetooth?: mixed, // Default: null
 *             browsing_topics?: mixed, // Default: null
 *             camera?: mixed, // Default: null
 *             captured_surface_control?: mixed, // Default: null
 *             compute_pressure?: mixed, // Default: null
 *             cross_origin_isolated?: mixed, // Default: null
 *             deferred_fetch?: mixed, // Default: null
 *             deferred_fetch_minimal?: mixed, // Default: null
 *             display_capture?: mixed, // Default: null
 *             encrypted_media?: mixed, // Default: null
 *             fullscreen?: mixed, // Default: null
 *             gamepad?: mixed, // Default: null
 *             geolocation?: mixed, // Default: null
 *             gyroscope?: mixed, // Default: null
 *             hid?: mixed, // Default: null
 *             identity_credentials_get?: mixed, // Default: null
 *             idle_detection?: mixed, // Default: null
 *             interest_cohort?: mixed, // Default: null
 *             language_detector?: mixed, // Default: null
 *             local_fonts?: mixed, // Default: null
 *             magnetometer?: mixed, // Default: null
 *             microphone?: mixed, // Default: null
 *             midi?: mixed, // Default: null
 *             otp_credentials?: mixed, // Default: null
 *             payment?: mixed, // Default: null
 *             picture_in_picture?: mixed, // Default: null
 *             publickey_credentials_create?: mixed, // Default: null
 *             publickey_credentials_get?: mixed, // Default: null
 *             screen_wake_lock?: mixed, // Default: null
 *             serial?: mixed, // Default: null
 *             speaker_selection?: mixed, // Default: null
 *             storage_access?: mixed, // Default: null
 *             summarizer?: mixed, // Default: null
 *             translator?: mixed, // Default: null
 *             usb?: mixed, // Default: null
 *             web_share?: mixed, // Default: null
 *             window_management?: mixed, // Default: null
 *             xr_spatial_tracking?: mixed, // Default: null
 *         },
 *     },
 * }
 * @psalm-type TurboConfig = array{
 *     broadcast?: bool|array{
 *         enabled?: bool, // Default: true
 *         entity_template_prefixes?: list<scalar|null>,
 *         doctrine_orm?: bool|array{ // Enable the Doctrine ORM integration
 *             enabled?: bool, // Default: true
 *         },
 *     },
 *     default_transport?: scalar|null, // Default: "default"
 * }
 * @psalm-type TfaWebauthnConfig = array{
 *     enabled?: scalar|null, // Default: false
 *     timeout?: int, // Default: 60000
 *     rpID?: scalar|null, // Default: null
 *     rpName?: scalar|null, // Default: "Webauthn Application"
 *     rpIcon?: scalar|null, // Default: null
 *     template?: scalar|null, // Default: "@TFAWebauthn/Authentication/form.html.twig"
 *     U2FAppID?: scalar|null, // Default: null
 * }
 * @psalm-type SchebTwoFactorConfig = array{
 *     persister?: scalar|null, // Default: "scheb_two_factor.persister.doctrine"
 *     model_manager_name?: scalar|null, // Default: null
 *     security_tokens?: list<scalar|null>,
 *     ip_whitelist?: list<scalar|null>,
 *     ip_whitelist_provider?: scalar|null, // Default: "scheb_two_factor.default_ip_whitelist_provider"
 *     two_factor_token_factory?: scalar|null, // Default: "scheb_two_factor.default_token_factory"
 *     two_factor_provider_decider?: scalar|null, // Default: "scheb_two_factor.default_provider_decider"
 *     two_factor_condition?: scalar|null, // Default: null
 *     code_reuse_cache?: scalar|null, // Default: null
 *     code_reuse_cache_duration?: int, // Default: 60
 *     code_reuse_default_handler?: scalar|null, // Default: null
 *     trusted_device?: bool|array{
 *         enabled?: scalar|null, // Default: false
 *         manager?: scalar|null, // Default: "scheb_two_factor.default_trusted_device_manager"
 *         lifetime?: int, // Default: 5184000
 *         extend_lifetime?: bool, // Default: false
 *         key?: scalar|null, // Default: null
 *         cookie_name?: scalar|null, // Default: "trusted_device"
 *         cookie_secure?: true|false|"auto", // Default: "auto"
 *         cookie_domain?: scalar|null, // Default: null
 *         cookie_path?: scalar|null, // Default: "/"
 *         cookie_same_site?: scalar|null, // Default: "lax"
 *     },
 *     backup_codes?: bool|array{
 *         enabled?: scalar|null, // Default: false
 *         manager?: scalar|null, // Default: "scheb_two_factor.default_backup_code_manager"
 *     },
 *     google?: bool|array{
 *         enabled?: scalar|null, // Default: false
 *         form_renderer?: scalar|null, // Default: null
 *         issuer?: scalar|null, // Default: null
 *         server_name?: scalar|null, // Default: null
 *         template?: scalar|null, // Default: "@SchebTwoFactor/Authentication/form.html.twig"
 *         digits?: int, // Default: 6
 *         leeway?: int, // Default: 0
 *     },
 * }
 * @psalm-type WebauthnConfig = array{
 *     fake_credential_generator?: scalar|null, // A service that implements the FakeCredentialGenerator to generate fake credentials for preventing username enumeration. // Default: "Webauthn\\SimpleFakeCredentialGenerator"
 *     clock?: scalar|null, // PSR-20 Clock service. // Default: "webauthn.clock.default"
 *     options_storage?: scalar|null, // Service responsible of the options/user entity storage during the ceremony // Default: "Webauthn\\Bundle\\Security\\Storage\\SessionStorage"
 *     event_dispatcher?: scalar|null, // PSR-14 Event Dispatcher service. // Default: "Psr\\EventDispatcher\\EventDispatcherInterface"
 *     http_client?: scalar|null, // A Symfony HTTP client. // Default: "webauthn.http_client.default"
 *     logger?: scalar|null, // A PSR-3 logger to receive logs during the processes // Default: "webauthn.logger.default"
 *     credential_repository?: scalar|null, // This repository is responsible of the credential storage // Default: "Webauthn\\Bundle\\Repository\\DummyPublicKeyCredentialSourceRepository"
 *     user_repository?: scalar|null, // This repository is responsible of the user storage // Default: "Webauthn\\Bundle\\Repository\\DummyPublicKeyCredentialUserEntityRepository"
 *     allowed_origins?: array<string, scalar|null>,
 *     allow_subdomains?: bool, // Default: false
 *     secured_rp_ids?: array<string, scalar|null>,
 *     counter_checker?: scalar|null, // This service will check if the counter is valid. By default it throws an exception (recommended). // Default: "Webauthn\\Counter\\ThrowExceptionIfInvalid"
 *     top_origin_validator?: scalar|null, // For cross origin (e.g. iframe), this service will be in charge of verifying the top origin. // Default: null
 *     creation_profiles?: array<string, array{ // Default: []
 *         rp: array{
 *             id?: scalar|null, // Default: null
 *             name: scalar|null,
 *             icon?: scalar|null, // Deprecated: The child node "icon" at path "webauthn.creation_profiles..rp.icon" is deprecated and has no effect. // Default: null
 *         },
 *         challenge_length?: int, // Default: 32
 *         timeout?: int, // Default: null
 *         authenticator_selection_criteria?: array{
 *             authenticator_attachment?: scalar|null, // Default: null
 *             require_resident_key?: bool, // Default: false
 *             user_verification?: scalar|null, // Default: "preferred"
 *             resident_key?: scalar|null, // Default: "preferred"
 *         },
 *         extensions?: array<string, scalar|null>,
 *         public_key_credential_parameters?: list<int>,
 *         attestation_conveyance?: scalar|null, // Default: "none"
 *     }>,
 *     request_profiles?: array<string, array{ // Default: []
 *         rp_id?: scalar|null, // Default: null
 *         challenge_length?: int, // Default: 32
 *         timeout?: int, // Default: null
 *         user_verification?: scalar|null, // Default: "preferred"
 *         extensions?: array<string, scalar|null>,
 *     }>,
 *     metadata?: bool|array{ // Enable the support of the Metadata Statements. Please read the documentation for this feature.
 *         enabled?: bool, // Default: false
 *         mds_repository: scalar|null, // The Metadata Statement repository.
 *         status_report_repository: scalar|null, // The Status Report repository.
 *         certificate_chain_checker?: scalar|null, // A Certificate Chain checker. // Default: "Webauthn\\MetadataService\\CertificateChain\\PhpCertificateChainValidator"
 *     },
 *     controllers?: bool|array{
 *         enabled?: bool, // Default: false
 *         creation?: array<string, array{ // Default: []
 *             options_method?: scalar|null, // Default: "POST"
 *             options_path: scalar|null,
 *             result_method?: scalar|null, // Default: "POST"
 *             result_path?: scalar|null, // Default: null
 *             host?: scalar|null, // Default: null
 *             profile?: scalar|null, // Default: "default"
 *             options_builder?: scalar|null, // When set, corresponds to the ID of the Public Key Credential Creation Builder. The profile-based ebuilder is ignored. // Default: null
 *             user_entity_guesser: scalar|null,
 *             hide_existing_credentials?: scalar|null, // In order to prevent username enumeration, the existing credentials can be hidden. This is highly recommended when the attestation ceremony is performed by anonymous users. // Default: false
 *             options_storage?: scalar|null, // Deprecated: The child node "options_storage" at path "webauthn.controllers.creation..options_storage" is deprecated. Please use the root option "options_storage" instead. // Service responsible of the options/user entity storage during the ceremony // Default: null
 *             success_handler?: scalar|null, // Default: "Webauthn\\Bundle\\Service\\DefaultSuccessHandler"
 *             failure_handler?: scalar|null, // Default: "Webauthn\\Bundle\\Service\\DefaultFailureHandler"
 *             options_handler?: scalar|null, // Default: "Webauthn\\Bundle\\Security\\Handler\\DefaultCreationOptionsHandler"
 *             allowed_origins?: array<string, scalar|null>,
 *             allow_subdomains?: bool, // Default: false
 *             secured_rp_ids?: array<string, scalar|null>,
 *         }>,
 *         request?: array<string, array{ // Default: []
 *             options_method?: scalar|null, // Default: "POST"
 *             options_path: scalar|null,
 *             result_method?: scalar|null, // Default: "POST"
 *             result_path?: scalar|null, // Default: null
 *             host?: scalar|null, // Default: null
 *             profile?: scalar|null, // Default: "default"
 *             options_builder?: scalar|null, // When set, corresponds to the ID of the Public Key Credential Creation Builder. The profile-based ebuilder is ignored. // Default: null
 *             options_storage?: scalar|null, // Deprecated: The child node "options_storage" at path "webauthn.controllers.request..options_storage" is deprecated. Please use the root option "options_storage" instead. // Service responsible of the options/user entity storage during the ceremony // Default: null
 *             success_handler?: scalar|null, // Default: "Webauthn\\Bundle\\Service\\DefaultSuccessHandler"
 *             failure_handler?: scalar|null, // Default: "Webauthn\\Bundle\\Service\\DefaultFailureHandler"
 *             options_handler?: scalar|null, // Default: "Webauthn\\Bundle\\Security\\Handler\\DefaultRequestOptionsHandler"
 *             allowed_origins?: array<string, scalar|null>,
 *             allow_subdomains?: bool, // Default: false
 *             secured_rp_ids?: array<string, scalar|null>,
 *         }>,
 *     },
 * }
 * @psalm-type NbgrpOneloginSamlConfig = array{ // nb:group OneLogin PHP Symfony Bundle configuration
 *     onelogin_settings?: array<string, array{ // Default: []
 *         baseurl?: scalar|null, // Default: "<request_scheme_and_host>/saml/"
 *         strict?: bool,
 *         debug?: bool,
 *         idp: array{
 *             entityId: scalar|null,
 *             singleSignOnService: array{
 *                 url: scalar|null,
 *                 binding?: scalar|null,
 *             },
 *             singleLogoutService?: array{
 *                 url?: scalar|null,
 *                 responseUrl?: scalar|null,
 *                 binding?: scalar|null,
 *             },
 *             x509cert?: scalar|null,
 *             certFingerprint?: scalar|null,
 *             certFingerprintAlgorithm?: "sha1"|"sha256"|"sha384"|"sha512",
 *             x509certMulti?: array{
 *                 signing?: list<scalar|null>,
 *                 encryption?: list<scalar|null>,
 *             },
 *         },
 *         sp?: array{
 *             entityId?: scalar|null, // Default: "<request_scheme_and_host>/saml/metadata"
 *             assertionConsumerService?: array{
 *                 url?: scalar|null, // Default: "<request_scheme_and_host>/saml/acs"
 *                 binding?: scalar|null,
 *             },
 *             attributeConsumingService?: array{
 *                 serviceName?: scalar|null,
 *                 serviceDescription?: scalar|null,
 *                 requestedAttributes?: list<array{ // Default: []
 *                     name?: scalar|null,
 *                     isRequired?: bool, // Default: false
 *                     nameFormat?: scalar|null,
 *                     friendlyName?: scalar|null,
 *                     attributeValue?: array<mixed>,
 *                 }>,
 *             },
 *             singleLogoutService?: array{
 *                 url?: scalar|null, // Default: "<request_scheme_and_host>/saml/logout"
 *                 binding?: scalar|null,
 *             },
 *             NameIDFormat?: scalar|null,
 *             x509cert?: scalar|null,
 *             privateKey?: scalar|null,
 *             x509certNew?: scalar|null,
 *         },
 *         compress?: array{
 *             requests?: bool,
 *             responses?: bool,
 *         },
 *         security?: array{
 *             nameIdEncrypted?: bool,
 *             authnRequestsSigned?: bool,
 *             logoutRequestSigned?: bool,
 *             logoutResponseSigned?: bool,
 *             signMetadata?: bool,
 *             wantMessagesSigned?: bool,
 *             wantAssertionsEncrypted?: bool,
 *             wantAssertionsSigned?: bool,
 *             wantNameId?: bool,
 *             wantNameIdEncrypted?: bool,
 *             requestedAuthnContext?: mixed,
 *             requestedAuthnContextComparison?: "exact"|"minimum"|"maximum"|"better",
 *             wantXMLValidation?: bool,
 *             relaxDestinationValidation?: bool,
 *             destinationStrictlyMatches?: bool,
 *             allowRepeatAttributeName?: bool,
 *             rejectUnsolicitedResponsesWithInResponseTo?: bool,
 *             signatureAlgorithm?: "http:\/\/www.w3.org\/2000\/09\/xmldsig#rsa-sha1"|"http:\/\/www.w3.org\/2000\/09\/xmldsig#dsa-sha1"|"http:\/\/www.w3.org\/2001\/04\/xmldsig-more#rsa-sha256"|"http:\/\/www.w3.org\/2001\/04\/xmldsig-more#rsa-sha384"|"http:\/\/www.w3.org\/2001\/04\/xmldsig-more#rsa-sha512",
 *             digestAlgorithm?: "http:\/\/www.w3.org\/2000\/09\/xmldsig#sha1"|"http:\/\/www.w3.org\/2001\/04\/xmlenc#sha256"|"http:\/\/www.w3.org\/2001\/04\/xmldsig-more#sha384"|"http:\/\/www.w3.org\/2001\/04\/xmlenc#sha512",
 *             encryption_algorithm?: "http:\/\/www.w3.org\/2001\/04\/xmlenc#tripledes-cbc"|"http:\/\/www.w3.org\/2001\/04\/xmlenc#aes128-cbc"|"http:\/\/www.w3.org\/2001\/04\/xmlenc#aes192-cbc"|"http:\/\/www.w3.org\/2001\/04\/xmlenc#aes256-cbc"|"http:\/\/www.w3.org\/2009\/xmlenc11#aes128-gcm"|"http:\/\/www.w3.org\/2009\/xmlenc11#aes192-gcm"|"http:\/\/www.w3.org\/2009\/xmlenc11#aes256-gcm",
 *             lowercaseUrlencoding?: bool,
 *         },
 *         contactPerson?: array{
 *             technical?: array{
 *                 givenName: scalar|null,
 *                 emailAddress: scalar|null,
 *             },
 *             support?: array{
 *                 givenName: scalar|null,
 *                 emailAddress: scalar|null,
 *             },
 *             administrative?: array{
 *                 givenName: scalar|null,
 *                 emailAddress: scalar|null,
 *             },
 *             billing?: array{
 *                 givenName: scalar|null,
 *                 emailAddress: scalar|null,
 *             },
 *             other?: array{
 *                 givenName: scalar|null,
 *                 emailAddress: scalar|null,
 *             },
 *         },
 *         organization?: list<array{ // Default: []
 *             name: scalar|null,
 *             displayname: scalar|null,
 *             url: scalar|null,
 *         }>,
 *     }>,
 *     use_proxy_vars?: bool, // Default: false
 *     idp_parameter_name?: scalar|null, // Default: "idp"
 *     entity_manager_name?: scalar|null,
 *     authn_request?: array{
 *         parameters?: list<scalar|null>,
 *         forceAuthn?: bool, // Default: false
 *         isPassive?: bool, // Default: false
 *         setNameIdPolicy?: bool, // Default: true
 *         nameIdValueReq?: scalar|null, // Default: null
 *     },
 * }
 * @psalm-type StimulusConfig = array{
 *     controller_paths?: list<scalar|null>,
 *     controllers_json?: scalar|null, // Default: "%kernel.project_dir%/assets/controllers.json"
 * }
 * @psalm-type UxTranslatorConfig = array{
 *     dump_directory?: scalar|null, // Default: "%kernel.project_dir%/var/translations"
 *     domains?: string|array{ // List of domains to include/exclude from the generated translations. Prefix with a `!` to exclude a domain.
 *         type?: scalar|null,
 *         elements?: list<scalar|null>,
 *     },
 * }
 * @psalm-type DompdfFontLoaderConfig = array{
 *     autodiscovery?: bool|array{
 *         paths?: list<scalar|null>,
 *         exclude_patterns?: list<scalar|null>,
 *         file_pattern?: scalar|null, // Default: "/\\.(ttf)$/"
 *         enabled?: bool, // Default: true
 *     },
 *     auto_install?: bool, // Default: false
 *     fonts?: list<array{ // Default: []
 *         normal: scalar|null,
 *         bold?: scalar|null,
 *         italic?: scalar|null,
 *         bold_italic?: scalar|null,
 *     }>,
 * }
 * @psalm-type KnpuOauth2ClientConfig = array{
 *     http_client?: scalar|null, // Service id of HTTP client to use (must implement GuzzleHttp\ClientInterface) // Default: null
 *     http_client_options?: array{
 *         timeout?: int,
 *         proxy?: scalar|null,
 *         verify?: bool, // Use only with proxy option set
 *     },
 *     clients?: array<string, array<string, mixed>>,
 * }
 * @psalm-type NelmioCorsConfig = array{
 *     defaults?: array{
 *         allow_credentials?: bool, // Default: false
 *         allow_origin?: list<scalar|null>,
 *         allow_headers?: list<scalar|null>,
 *         allow_methods?: list<scalar|null>,
 *         allow_private_network?: bool, // Default: false
 *         expose_headers?: list<scalar|null>,
 *         max_age?: scalar|null, // Default: 0
 *         hosts?: list<scalar|null>,
 *         origin_regex?: bool, // Default: false
 *         forced_allow_origin_value?: scalar|null, // Default: null
 *         skip_same_as_origin?: bool, // Default: true
 *     },
 *     paths?: array<string, array{ // Default: []
 *         allow_credentials?: bool,
 *         allow_origin?: list<scalar|null>,
 *         allow_headers?: list<scalar|null>,
 *         allow_methods?: list<scalar|null>,
 *         allow_private_network?: bool,
 *         expose_headers?: list<scalar|null>,
 *         max_age?: scalar|null, // Default: 0
 *         hosts?: list<scalar|null>,
 *         origin_regex?: bool,
 *         forced_allow_origin_value?: scalar|null, // Default: null
 *         skip_same_as_origin?: bool,
 *     }>,
 * }
 * @psalm-type JbtronicsSettingsConfig = array{
 *     search_paths?: list<scalar|null>,
 *     proxy_dir?: scalar|null, // Default: "%kernel.cache_dir%/jbtronics_settings/proxies"
 *     proxy_namespace?: scalar|null, // Default: "Jbtronics\\SettingsBundle\\Proxies"
 *     default_storage_adapter?: scalar|null, // Default: null
 *     save_after_migration?: bool, // Default: true
 *     file_storage?: array{
 *         storage_directory?: scalar|null, // Default: "%kernel.project_dir%/var/jbtronics_settings/"
 *         default_filename?: scalar|null, // Default: "settings"
 *     },
 *     orm_storage?: array{
 *         default_entity_class?: scalar|null, // Default: null
 *         prefetch_all?: bool, // Default: true
 *     },
 *     cache?: array{
 *         service?: scalar|null, // Default: "cache.app.taggable"
 *         default_cacheable?: bool, // Default: false
 *         ttl?: int, // Default: 0
 *         invalidate_on_env_change?: bool, // Default: true
 *     },
 * }
 * @psalm-type JbtronicsTranslationEditorConfig = array{
 *     translations_path?: scalar|null, // Default: "%translator.default_path%"
 *     format?: scalar|null, // Default: "xlf"
 *     xliff_version?: scalar|null, // Default: "2.0"
 *     use_intl_icu_format?: bool, // Default: false
 *     writer_options?: list<scalar|null>,
 * }
 * @psalm-type ApiPlatformConfig = array{
 *     title?: scalar|null, // The title of the API. // Default: ""
 *     description?: scalar|null, // The description of the API. // Default: ""
 *     version?: scalar|null, // The version of the API. // Default: "0.0.0"
 *     show_webby?: bool, // If true, show Webby on the documentation page // Default: true
 *     use_symfony_listeners?: bool, // Uses Symfony event listeners instead of the ApiPlatform\Symfony\Controller\MainController. // Default: false
 *     name_converter?: scalar|null, // Specify a name converter to use. // Default: null
 *     asset_package?: scalar|null, // Specify an asset package name to use. // Default: null
 *     path_segment_name_generator?: scalar|null, // Specify a path name generator to use. // Default: "api_platform.metadata.path_segment_name_generator.underscore"
 *     inflector?: scalar|null, // Specify an inflector to use. // Default: "api_platform.metadata.inflector"
 *     validator?: array{
 *         serialize_payload_fields?: mixed, // Set to null to serialize all payload fields when a validation error is thrown, or set the fields you want to include explicitly. // Default: []
 *         query_parameter_validation?: bool, // Deprecated: Will be removed in API Platform 5.0. // Default: true
 *     },
 *     eager_loading?: bool|array{
 *         enabled?: bool, // Default: true
 *         fetch_partial?: bool, // Fetch only partial data according to serialization groups. If enabled, Doctrine ORM entities will not work as expected if any of the other fields are used. // Default: false
 *         max_joins?: int, // Max number of joined relations before EagerLoading throws a RuntimeException // Default: 30
 *         force_eager?: bool, // Force join on every relation. If disabled, it will only join relations having the EAGER fetch mode. // Default: true
 *     },
 *     handle_symfony_errors?: bool, // Allows to handle symfony exceptions. // Default: false
 *     enable_swagger?: bool, // Enable the Swagger documentation and export. // Default: true
 *     enable_json_streamer?: bool, // Enable json streamer. // Default: false
 *     enable_swagger_ui?: bool, // Enable Swagger UI // Default: true
 *     enable_re_doc?: bool, // Enable ReDoc // Default: true
 *     enable_entrypoint?: bool, // Enable the entrypoint // Default: true
 *     enable_docs?: bool, // Enable the docs // Default: true
 *     enable_profiler?: bool, // Enable the data collector and the WebProfilerBundle integration. // Default: true
 *     enable_phpdoc_parser?: bool, // Enable resource metadata collector using PHPStan PhpDocParser. // Default: true
 *     enable_link_security?: bool, // Enable security for Links (sub resources) // Default: false
 *     collection?: array{
 *         exists_parameter_name?: scalar|null, // The name of the query parameter to filter on nullable field values. // Default: "exists"
 *         order?: scalar|null, // The default order of results. // Default: "ASC"
 *         order_parameter_name?: scalar|null, // The name of the query parameter to order results. // Default: "order"
 *         order_nulls_comparison?: "nulls_smallest"|"nulls_largest"|"nulls_always_first"|"nulls_always_last"|null, // The nulls comparison strategy. // Default: null
 *         pagination?: bool|array{
 *             enabled?: bool, // Default: true
 *             page_parameter_name?: scalar|null, // The default name of the parameter handling the page number. // Default: "page"
 *             enabled_parameter_name?: scalar|null, // The name of the query parameter to enable or disable pagination. // Default: "pagination"
 *             items_per_page_parameter_name?: scalar|null, // The name of the query parameter to set the number of items per page. // Default: "itemsPerPage"
 *             partial_parameter_name?: scalar|null, // The name of the query parameter to enable or disable partial pagination. // Default: "partial"
 *         },
 *     },
 *     mapping?: array{
 *         imports?: list<scalar|null>,
 *         paths?: list<scalar|null>,
 *     },
 *     resource_class_directories?: list<scalar|null>,
 *     serializer?: array{
 *         hydra_prefix?: bool, // Use the "hydra:" prefix. // Default: false
 *     },
 *     doctrine?: bool|array{
 *         enabled?: bool, // Default: true
 *     },
 *     doctrine_mongodb_odm?: bool|array{
 *         enabled?: bool, // Default: false
 *     },
 *     oauth?: bool|array{
 *         enabled?: bool, // Default: false
 *         clientId?: scalar|null, // The oauth client id. // Default: ""
 *         clientSecret?: scalar|null, // The OAuth client secret. Never use this parameter in your production environment. It exposes crucial security information. This feature is intended for dev/test environments only. Enable "oauth.pkce" instead // Default: ""
 *         pkce?: bool, // Enable the oauth PKCE. // Default: false
 *         type?: scalar|null, // The oauth type. // Default: "oauth2"
 *         flow?: scalar|null, // The oauth flow grant type. // Default: "application"
 *         tokenUrl?: scalar|null, // The oauth token url. // Default: ""
 *         authorizationUrl?: scalar|null, // The oauth authentication url. // Default: ""
 *         refreshUrl?: scalar|null, // The oauth refresh url. // Default: ""
 *         scopes?: list<scalar|null>,
 *     },
 *     graphql?: bool|array{
 *         enabled?: bool, // Default: false
 *         default_ide?: scalar|null, // Default: "graphiql"
 *         graphiql?: bool|array{
 *             enabled?: bool, // Default: false
 *         },
 *         introspection?: bool|array{
 *             enabled?: bool, // Default: true
 *         },
 *         max_query_depth?: int, // Default: 20
 *         graphql_playground?: array<mixed>,
 *         max_query_complexity?: int, // Default: 500
 *         nesting_separator?: scalar|null, // The separator to use to filter nested fields. // Default: "_"
 *         collection?: array{
 *             pagination?: bool|array{
 *                 enabled?: bool, // Default: true
 *             },
 *         },
 *     },
 *     swagger?: array{
 *         persist_authorization?: bool, // Persist the SwaggerUI Authorization in the localStorage. // Default: false
 *         versions?: list<scalar|null>,
 *         api_keys?: array<string, array{ // Default: []
 *             name?: scalar|null, // The name of the header or query parameter containing the api key.
 *             type?: "query"|"header", // Whether the api key should be a query parameter or a header.
 *         }>,
 *         http_auth?: array<string, array{ // Default: []
 *             scheme?: scalar|null, // The OpenAPI HTTP auth scheme, for example "bearer"
 *             bearerFormat?: scalar|null, // The OpenAPI HTTP bearer format
 *         }>,
 *         swagger_ui_extra_configuration?: mixed, // To pass extra configuration to Swagger UI, like docExpansion or filter. // Default: []
 *     },
 *     http_cache?: array{
 *         public?: bool|null, // To make all responses public by default. // Default: null
 *         invalidation?: bool|array{ // Enable the tags-based cache invalidation system.
 *             enabled?: bool, // Default: false
 *             varnish_urls?: list<scalar|null>,
 *             urls?: list<scalar|null>,
 *             scoped_clients?: list<scalar|null>,
 *             max_header_length?: int, // Max header length supported by the cache server. // Default: 7500
 *             request_options?: mixed, // To pass options to the client charged with the request. // Default: []
 *             purger?: scalar|null, // Specify a purger to use (available values: "api_platform.http_cache.purger.varnish.ban", "api_platform.http_cache.purger.varnish.xkey", "api_platform.http_cache.purger.souin"). // Default: "api_platform.http_cache.purger.varnish"
 *             xkey?: array{ // Deprecated: The "xkey" configuration is deprecated, use your own purger to customize surrogate keys or the appropriate paramters.
 *                 glue?: scalar|null, // xkey glue between keys // Default: " "
 *             },
 *         },
 *     },
 *     mercure?: bool|array{
 *         enabled?: bool, // Default: false
 *         hub_url?: scalar|null, // The URL sent in the Link HTTP header. If not set, will default to the URL for MercureBundle's default hub. // Default: null
 *         include_type?: bool, // Always include @type in updates (including delete ones). // Default: false
 *     },
 *     messenger?: bool|array{
 *         enabled?: bool, // Default: false
 *     },
 *     elasticsearch?: bool|array{
 *         enabled?: bool, // Default: false
 *         hosts?: list<scalar|null>,
 *     },
 *     openapi?: array{
 *         contact?: array{
 *             name?: scalar|null, // The identifying name of the contact person/organization. // Default: null
 *             url?: scalar|null, // The URL pointing to the contact information. MUST be in the format of a URL. // Default: null
 *             email?: scalar|null, // The email address of the contact person/organization. MUST be in the format of an email address. // Default: null
 *         },
 *         termsOfService?: scalar|null, // A URL to the Terms of Service for the API. MUST be in the format of a URL. // Default: null
 *         tags?: list<array{ // Default: []
 *             name: scalar|null,
 *             description?: scalar|null, // Default: null
 *         }>,
 *         license?: array{
 *             name?: scalar|null, // The license name used for the API. // Default: null
 *             url?: scalar|null, // URL to the license used for the API. MUST be in the format of a URL. // Default: null
 *             identifier?: scalar|null, // An SPDX license expression for the API. The identifier field is mutually exclusive of the url field. // Default: null
 *         },
 *         swagger_ui_extra_configuration?: mixed, // To pass extra configuration to Swagger UI, like docExpansion or filter. // Default: []
 *         overrideResponses?: bool, // Whether API Platform adds automatic responses to the OpenAPI documentation. // Default: true
 *         error_resource_class?: scalar|null, // The class used to represent errors in the OpenAPI documentation. // Default: null
 *         validation_error_resource_class?: scalar|null, // The class used to represent validation errors in the OpenAPI documentation. // Default: null
 *     },
 *     maker?: bool|array{
 *         enabled?: bool, // Default: true
 *     },
 *     exception_to_status?: array<string, int>,
 *     formats?: array<string, array{ // Default: {"jsonld":{"mime_types":["application/ld+json"]}}
 *         mime_types?: list<scalar|null>,
 *     }>,
 *     patch_formats?: array<string, array{ // Default: {"json":{"mime_types":["application/merge-patch+json"]}}
 *         mime_types?: list<scalar|null>,
 *     }>,
 *     docs_formats?: array<string, array{ // Default: {"jsonld":{"mime_types":["application/ld+json"]},"jsonopenapi":{"mime_types":["application/vnd.openapi+json"]},"html":{"mime_types":["text/html"]},"yamlopenapi":{"mime_types":["application/vnd.openapi+yaml"]}}
 *         mime_types?: list<scalar|null>,
 *     }>,
 *     error_formats?: array<string, array{ // Default: {"jsonld":{"mime_types":["application/ld+json"]},"jsonproblem":{"mime_types":["application/problem+json"]},"json":{"mime_types":["application/problem+json","application/json"]}}
 *         mime_types?: list<scalar|null>,
 *     }>,
 *     jsonschema_formats?: list<scalar|null>,
 *     defaults?: array{
 *         uri_template?: mixed,
 *         short_name?: mixed,
 *         description?: mixed,
 *         types?: mixed,
 *         operations?: mixed,
 *         formats?: mixed,
 *         input_formats?: mixed,
 *         output_formats?: mixed,
 *         uri_variables?: mixed,
 *         route_prefix?: mixed,
 *         defaults?: mixed,
 *         requirements?: mixed,
 *         options?: mixed,
 *         stateless?: mixed,
 *         sunset?: mixed,
 *         accept_patch?: mixed,
 *         status?: mixed,
 *         host?: mixed,
 *         schemes?: mixed,
 *         condition?: mixed,
 *         controller?: mixed,
 *         class?: mixed,
 *         url_generation_strategy?: mixed,
 *         deprecation_reason?: mixed,
 *         headers?: mixed,
 *         cache_headers?: mixed,
 *         normalization_context?: mixed,
 *         denormalization_context?: mixed,
 *         collect_denormalization_errors?: mixed,
 *         hydra_context?: mixed,
 *         openapi?: mixed,
 *         validation_context?: mixed,
 *         filters?: mixed,
 *         mercure?: mixed,
 *         messenger?: mixed,
 *         input?: mixed,
 *         output?: mixed,
 *         order?: mixed,
 *         fetch_partial?: mixed,
 *         force_eager?: mixed,
 *         pagination_client_enabled?: mixed,
 *         pagination_client_items_per_page?: mixed,
 *         pagination_client_partial?: mixed,
 *         pagination_via_cursor?: mixed,
 *         pagination_enabled?: mixed,
 *         pagination_fetch_join_collection?: mixed,
 *         pagination_use_output_walkers?: mixed,
 *         pagination_items_per_page?: mixed,
 *         pagination_maximum_items_per_page?: mixed,
 *         pagination_partial?: mixed,
 *         pagination_type?: mixed,
 *         security?: mixed,
 *         security_message?: mixed,
 *         security_post_denormalize?: mixed,
 *         security_post_denormalize_message?: mixed,
 *         security_post_validation?: mixed,
 *         security_post_validation_message?: mixed,
 *         composite_identifier?: mixed,
 *         exception_to_status?: mixed,
 *         query_parameter_validation_enabled?: mixed,
 *         links?: mixed,
 *         graph_ql_operations?: mixed,
 *         provider?: mixed,
 *         processor?: mixed,
 *         state_options?: mixed,
 *         rules?: mixed,
 *         policy?: mixed,
 *         middleware?: mixed,
 *         parameters?: mixed,
 *         strict_query_parameter_validation?: mixed,
 *         hide_hydra_operation?: mixed,
 *         json_stream?: mixed,
 *         extra_properties?: mixed,
 *         map?: mixed,
 *         route_name?: mixed,
 *         errors?: mixed,
 *         read?: mixed,
 *         deserialize?: mixed,
 *         validate?: mixed,
 *         write?: mixed,
 *         serialize?: mixed,
 *         priority?: mixed,
 *         name?: mixed,
 *         allow_create?: mixed,
 *         item_uri_template?: mixed,
 *         ...<mixed>
 *     },
 * }
 * @psalm-type DamaDoctrineTestConfig = array{
 *     enable_static_connection?: mixed, // Default: true
 *     enable_static_meta_data_cache?: bool, // Default: true
 *     enable_static_query_cache?: bool, // Default: true
 *     connection_keys?: list<mixed>,
 * }
 * @psalm-type ConfigType = array{
 *     imports?: ImportsConfig,
 *     parameters?: ParametersConfig,
 *     services?: ServicesConfig,
 *     framework?: FrameworkConfig,
 *     doctrine?: DoctrineConfig,
 *     doctrine_migrations?: DoctrineMigrationsConfig,
 *     security?: SecurityConfig,
 *     twig?: TwigConfig,
 *     monolog?: MonologConfig,
 *     webpack_encore?: WebpackEncoreConfig,
 *     datatables?: DatatablesConfig,
 *     liip_imagine?: LiipImagineConfig,
 *     twig_extra?: TwigExtraConfig,
 *     gregwar_captcha?: GregwarCaptchaConfig,
 *     florianv_swap?: FlorianvSwapConfig,
 *     nelmio_security?: NelmioSecurityConfig,
 *     turbo?: TurboConfig,
 *     tfa_webauthn?: TfaWebauthnConfig,
 *     scheb_two_factor?: SchebTwoFactorConfig,
 *     webauthn?: WebauthnConfig,
 *     nbgrp_onelogin_saml?: NbgrpOneloginSamlConfig,
 *     stimulus?: StimulusConfig,
 *     ux_translator?: UxTranslatorConfig,
 *     dompdf_font_loader?: DompdfFontLoaderConfig,
 *     knpu_oauth2_client?: KnpuOauth2ClientConfig,
 *     nelmio_cors?: NelmioCorsConfig,
 *     jbtronics_settings?: JbtronicsSettingsConfig,
 *     api_platform?: ApiPlatformConfig,
 *     "when@dev"?: array{
 *         imports?: ImportsConfig,
 *         parameters?: ParametersConfig,
 *         services?: ServicesConfig,
 *         framework?: FrameworkConfig,
 *         doctrine?: DoctrineConfig,
 *         doctrine_migrations?: DoctrineMigrationsConfig,
 *         security?: SecurityConfig,
 *         twig?: TwigConfig,
 *         web_profiler?: WebProfilerConfig,
 *         monolog?: MonologConfig,
 *         debug?: DebugConfig,
 *         maker?: MakerConfig,
 *         webpack_encore?: WebpackEncoreConfig,
 *         datatables?: DatatablesConfig,
 *         liip_imagine?: LiipImagineConfig,
 *         twig_extra?: TwigExtraConfig,
 *         gregwar_captcha?: GregwarCaptchaConfig,
 *         florianv_swap?: FlorianvSwapConfig,
 *         nelmio_security?: NelmioSecurityConfig,
 *         turbo?: TurboConfig,
 *         tfa_webauthn?: TfaWebauthnConfig,
 *         scheb_two_factor?: SchebTwoFactorConfig,
 *         webauthn?: WebauthnConfig,
 *         nbgrp_onelogin_saml?: NbgrpOneloginSamlConfig,
 *         stimulus?: StimulusConfig,
 *         ux_translator?: UxTranslatorConfig,
 *         dompdf_font_loader?: DompdfFontLoaderConfig,
 *         knpu_oauth2_client?: KnpuOauth2ClientConfig,
 *         nelmio_cors?: NelmioCorsConfig,
 *         jbtronics_settings?: JbtronicsSettingsConfig,
 *         jbtronics_translation_editor?: JbtronicsTranslationEditorConfig,
 *         api_platform?: ApiPlatformConfig,
 *     },
 *     "when@docker"?: array{
 *         imports?: ImportsConfig,
 *         parameters?: ParametersConfig,
 *         services?: ServicesConfig,
 *         framework?: FrameworkConfig,
 *         doctrine?: DoctrineConfig,
 *         doctrine_migrations?: DoctrineMigrationsConfig,
 *         security?: SecurityConfig,
 *         twig?: TwigConfig,
 *         monolog?: MonologConfig,
 *         webpack_encore?: WebpackEncoreConfig,
 *         datatables?: DatatablesConfig,
 *         liip_imagine?: LiipImagineConfig,
 *         twig_extra?: TwigExtraConfig,
 *         gregwar_captcha?: GregwarCaptchaConfig,
 *         florianv_swap?: FlorianvSwapConfig,
 *         nelmio_security?: NelmioSecurityConfig,
 *         turbo?: TurboConfig,
 *         tfa_webauthn?: TfaWebauthnConfig,
 *         scheb_two_factor?: SchebTwoFactorConfig,
 *         webauthn?: WebauthnConfig,
 *         nbgrp_onelogin_saml?: NbgrpOneloginSamlConfig,
 *         stimulus?: StimulusConfig,
 *         ux_translator?: UxTranslatorConfig,
 *         dompdf_font_loader?: DompdfFontLoaderConfig,
 *         knpu_oauth2_client?: KnpuOauth2ClientConfig,
 *         nelmio_cors?: NelmioCorsConfig,
 *         jbtronics_settings?: JbtronicsSettingsConfig,
 *         api_platform?: ApiPlatformConfig,
 *     },
 *     "when@prod"?: array{
 *         imports?: ImportsConfig,
 *         parameters?: ParametersConfig,
 *         services?: ServicesConfig,
 *         framework?: FrameworkConfig,
 *         doctrine?: DoctrineConfig,
 *         doctrine_migrations?: DoctrineMigrationsConfig,
 *         security?: SecurityConfig,
 *         twig?: TwigConfig,
 *         monolog?: MonologConfig,
 *         webpack_encore?: WebpackEncoreConfig,
 *         datatables?: DatatablesConfig,
 *         liip_imagine?: LiipImagineConfig,
 *         twig_extra?: TwigExtraConfig,
 *         gregwar_captcha?: GregwarCaptchaConfig,
 *         florianv_swap?: FlorianvSwapConfig,
 *         nelmio_security?: NelmioSecurityConfig,
 *         turbo?: TurboConfig,
 *         tfa_webauthn?: TfaWebauthnConfig,
 *         scheb_two_factor?: SchebTwoFactorConfig,
 *         webauthn?: WebauthnConfig,
 *         nbgrp_onelogin_saml?: NbgrpOneloginSamlConfig,
 *         stimulus?: StimulusConfig,
 *         ux_translator?: UxTranslatorConfig,
 *         dompdf_font_loader?: DompdfFontLoaderConfig,
 *         knpu_oauth2_client?: KnpuOauth2ClientConfig,
 *         nelmio_cors?: NelmioCorsConfig,
 *         jbtronics_settings?: JbtronicsSettingsConfig,
 *         api_platform?: ApiPlatformConfig,
 *     },
 *     "when@test"?: array{
 *         imports?: ImportsConfig,
 *         parameters?: ParametersConfig,
 *         services?: ServicesConfig,
 *         framework?: FrameworkConfig,
 *         doctrine?: DoctrineConfig,
 *         doctrine_migrations?: DoctrineMigrationsConfig,
 *         security?: SecurityConfig,
 *         twig?: TwigConfig,
 *         web_profiler?: WebProfilerConfig,
 *         monolog?: MonologConfig,
 *         debug?: DebugConfig,
 *         webpack_encore?: WebpackEncoreConfig,
 *         datatables?: DatatablesConfig,
 *         liip_imagine?: LiipImagineConfig,
 *         dama_doctrine_test?: DamaDoctrineTestConfig,
 *         twig_extra?: TwigExtraConfig,
 *         gregwar_captcha?: GregwarCaptchaConfig,
 *         florianv_swap?: FlorianvSwapConfig,
 *         nelmio_security?: NelmioSecurityConfig,
 *         turbo?: TurboConfig,
 *         tfa_webauthn?: TfaWebauthnConfig,
 *         scheb_two_factor?: SchebTwoFactorConfig,
 *         webauthn?: WebauthnConfig,
 *         nbgrp_onelogin_saml?: NbgrpOneloginSamlConfig,
 *         stimulus?: StimulusConfig,
 *         ux_translator?: UxTranslatorConfig,
 *         dompdf_font_loader?: DompdfFontLoaderConfig,
 *         knpu_oauth2_client?: KnpuOauth2ClientConfig,
 *         nelmio_cors?: NelmioCorsConfig,
 *         jbtronics_settings?: JbtronicsSettingsConfig,
 *         api_platform?: ApiPlatformConfig,
 *     },
 *     ...<string, ExtensionType|array{ // extra keys must follow the when@%env% pattern or match an extension alias
 *         imports?: ImportsConfig,
 *         parameters?: ParametersConfig,
 *         services?: ServicesConfig,
 *         ...<string, ExtensionType>,
 *     }>
 * }
 */
final class App
{
    /**
     * @param ConfigType $config
     *
     * @psalm-return ConfigType
     */
    public static function config(array $config): array
    {
        return AppReference::config($config);
    }
}

namespace Symfony\Component\Routing\Loader\Configurator;

/**
 * This class provides array-shapes for configuring the routes of an application.
 *
 * Example:
 *
 *     ```php
 *     // config/routes.php
 *     namespace Symfony\Component\Routing\Loader\Configurator;
 *
 *     return Routes::config([
 *         'controllers' => [
 *             'resource' => 'routing.controllers',
 *         ],
 *     ]);
 *     ```
 *
 * @psalm-type RouteConfig = array{
 *     path: string|array<string,string>,
 *     controller?: string,
 *     methods?: string|list<string>,
 *     requirements?: array<string,string>,
 *     defaults?: array<string,mixed>,
 *     options?: array<string,mixed>,
 *     host?: string|array<string,string>,
 *     schemes?: string|list<string>,
 *     condition?: string,
 *     locale?: string,
 *     format?: string,
 *     utf8?: bool,
 *     stateless?: bool,
 * }
 * @psalm-type ImportConfig = array{
 *     resource: string,
 *     type?: string,
 *     exclude?: string|list<string>,
 *     prefix?: string|array<string,string>,
 *     name_prefix?: string,
 *     trailing_slash_on_root?: bool,
 *     controller?: string,
 *     methods?: string|list<string>,
 *     requirements?: array<string,string>,
 *     defaults?: array<string,mixed>,
 *     options?: array<string,mixed>,
 *     host?: string|array<string,string>,
 *     schemes?: string|list<string>,
 *     condition?: string,
 *     locale?: string,
 *     format?: string,
 *     utf8?: bool,
 *     stateless?: bool,
 * }
 * @psalm-type AliasConfig = array{
 *     alias: string,
 *     deprecated?: array{package:string, version:string, message?:string},
 * }
 * @psalm-type RoutesConfig = array{
 *     "when@dev"?: array<string, RouteConfig|ImportConfig|AliasConfig>,
 *     "when@docker"?: array<string, RouteConfig|ImportConfig|AliasConfig>,
 *     "when@prod"?: array<string, RouteConfig|ImportConfig|AliasConfig>,
 *     "when@test"?: array<string, RouteConfig|ImportConfig|AliasConfig>,
 *     ...<string, RouteConfig|ImportConfig|AliasConfig>
 * }
 */
final class Routes
{
    /**
     * @param RoutesConfig $config
     *
     * @psalm-return RoutesConfig
     */
    public static function config(array $config): array
    {
        return $config;
    }
}
