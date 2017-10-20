Feature: Check PHP compatibility

  Scenario: Check compatibility of a default WP install
    Given a WP install

    When I run `wp php-compat --fields=name,type,compat`
    Then STDOUT should be a table containing rows:
      | name        | type       | compat         |
      | wordpress   | core       | success        |
      | akismet     | plugin     | success        |
