Feature: Check PHP compatibility

  Scenario: Check compatibility of a default WP install
    Given a WP install

    When I run `wp php-compat --fields=name,type,compat`
    Then STDOUT should be a table containing rows:
      | name        | type       | compat         |
      | wordpress   | core       | success        |
      | akismet     | plugin     | success        |

  Scenario: Check compatibility of a default WP install with cache enabled
    Given a WP install
    And I run `mkdir php-compat-cache`
    And I run `wp plugin update --all`

    When I run `wp --require={SRC_DIR}/bin/php-compat-cache.php php-compat-cache plugin akismet php-compat-cache --prior_versions=1`
    Then STDOUT should contain:
      """
      Success:
      """

    When I run `WP_CLI_PHP_COMPAT_CACHE=php-compat-cache wp php-compat --fields=name,type,compat,time`
    Then STDOUT should be a table containing rows:
      | name        | type       | compat         | time      |
      | wordpress   | core       | success        | cached    |
      | akismet     | plugin     | success        | cached    |
