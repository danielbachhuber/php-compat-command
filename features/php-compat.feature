Feature: Check PHP compatibility

  @require-wp-latest
  Scenario: Check compatibility of a default WP install
    Given a WP install

    When I run `wp php-compat --fields=name,type,compat --format=csv`
    Then STDOUT should be CSV containing:
      | name      | type   | compat  |
      | wordpress | core   | success |
      | akismet   | plugin | success |

  @require-wp-latest
  Scenario: Check compatibility of a default WP install with cache enabled
    Given a WP install
    And I run `wp plugin uninstall akismet`
    And I run `wp plugin install one-time-login`
    And I run `mkdir php-compat-cache`
    And I run `wp plugin update --all`

    When I run `wp php-compat-cache plugin one-time-login php-compat-cache --prior_versions=1`
    Then STDOUT should contain:
      """
      Success:
      """

    When I run `WP_CLI_PHP_COMPAT_CACHE=php-compat-cache wp php-compat --fields=name,type,compat,time`
    Then STDOUT should be a table containing rows:
      | name           | type   | compat  | time   |
      | wordpress      | core   | success | cached |
      | one-time-login | plugin | success | cached |

  @broken
  Scenario: Check compatibility of Co-Authors Plus for specific PHP versions
    Given a WP install
    # Version 3.2.2 has a known PHP 5.2 incompatibility
    And I run `wp plugin install co-authors-plus --version=3.2.2`
    And I run `mkdir php-compat-cache`

    When I run `wp php-compat-cache plugin co-authors-plus php-compat-cache --version=3.2.2`
    Then STDOUT should contain:
      """
      Downloading co-authors-plus version 3.2.2 (1/1)
      """
    And STDOUT should contain:
      """
      Success:
      """

    When I run `wp php-compat --fields=name,type,compat`
    Then STDOUT should be a table containing rows:
      | name            | type   | compat  |
      | co-authors-plus | plugin | success |

    When I run `WP_CLI_PHP_COMPAT_CACHE=php-compat-cache wp php-compat --fields=name,type,compat,time`
    Then STDOUT should be a table containing rows:
      | name            | type   | compat  | time   |
      | co-authors-plus | plugin | success | cached |

    When I run `wp php-compat --fields=name,type,compat --php_version=5.2`
    Then STDOUT should be a table containing rows:
      | name            | type   | compat  |
      | co-authors-plus | plugin | failure |

    When I run `WP_CLI_PHP_COMPAT_CACHE=php-compat-cache wp php-compat --fields=name,type,compat,time --php_version=5.2`
    Then STDOUT should be a table containing rows:
      | name            | type   | compat  | time   |
      | co-authors-plus | plugin | failure | cached |

    When I run `WP_CLI_PHP_COMPAT_CACHE=php-compat-cache wp php-compat --fields=name,type,compat,time --php_version=5.2-`
    Then STDOUT should be a table containing rows:
      | name            | type   | compat  | time   |
      | co-authors-plus | plugin | failure | cached |

    When I run `WP_CLI_PHP_COMPAT_CACHE=php-compat-cache wp php-compat --fields=name,type,compat,time --php_version=5.3-`
    Then STDOUT should be a table containing rows:
      | name            | type   | compat  | time   |
      | co-authors-plus | plugin | success | cached |

  @broken
  Scenario: Plugin formally supports PHP 7 in a newer version
    Given a WP install
    And a php-compat-cache/plugins-php7-compat.txt file:
      """
      # Plugins and the minimum version they have formal PHP 7 compat.
      woocommerce,3.3.0
      """
    And I run `wp plugin install woocommerce --version=3.2.6`

    When I run `WP_CLI_PHP_COMPAT_CACHE=php-compat-cache wp php-compat --fields=name,type,compat,time`
    Then STDOUT should be a table containing rows:
      | name        | type   | compat      | time   |
      | woocommerce | plugin | with-update | cached |

    When I run `wp plugin update woocommerce`
    Then STDOUT should contain:
      """
      Success:
      """

    When I run `WP_CLI_PHP_COMPAT_CACHE=php-compat-cache wp php-compat --fields=name,type,compat`
    Then STDOUT should be a table containing rows:
      | name        | type   | compat  |
      | woocommerce | plugin | success |

  @require-wp-latest
  Scenario: Invalid php_version argument specified
    Given a WP install

    When I try `wp php-compat --php_version=5`
    Then STDERR should be:
      """
      Error: php_version must match ^[\d]\.[\d]-?$
      """

    When I try `wp php-compat --php_version=5-`
    Then STDERR should be:
      """
      Error: php_version must match ^[\d]\.[\d]-?$
      """

    When I try `wp php-compat --php_version=5.6-7.0`
    Then STDERR should be:
      """
      Error: php_version must match ^[\d]\.[\d]-?$
      """
