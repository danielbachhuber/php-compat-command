danielbachhuber/php-compat-command
==================================

Scan WordPress, plugins and themes for PHP version compatibility.

[![Build Status](https://travis-ci.org/danielbachhuber/php-compat-command.svg?branch=master)](https://travis-ci.org/danielbachhuber/php-compat-command)

Quick links: [Using](#using) | [Installing](#installing) | [Contributing](#contributing) | [Support](#support)

## Using

~~~
wp php-compat [--path=<path>] [--fields=<fields>]
~~~

Uses the [PHPCompatibility PHPCS sniffs](https://github.com/wimg/PHPCompatibility)
and interprets the WordPress-specific results.

Speed up the scanning process by using [php-compat-cache](https://github.com/danielbachhuber/php-compat-cache), a collection of pre-scanned WordPress.org
plugins and themes.

**OPTIONS**

	[--path=<path>]
		Path to the WordPress install. Defaults to current directory.

	[--fields=<fields>]
		Limit output to specific fields.

**EXAMPLES**

    # Check compatibility of a WordPress install in the 'danielbachhuber' path
    $ wp php-compat --path=danielbachhuber
    +-----------------------+--------+---------+---------+-------+-------+
    | name                  | type   | compat  | version | time  | files |
    +-----------------------+--------+---------+---------+-------+-------+
    | wordpress             | core   | success | 4.7.6   |       |       |
    | akismet               | plugin | success | 3.2     | 1.39s | 13    |
    | debug-bar             | plugin | success | 0.8.4   | 0.29s | 10    |
    | oembed-gist           | plugin | success | 4.7.1   | 0.08s | 1     |
    | danielbachhuber-theme | theme  | success | 0.0.0   | 0.81s | 30    |
    | twentyfifteen         | theme  | success | 1.7     | 0.42s | 22    |
    | twentyseventeen       | theme  | success | 1.1     | 0.63s | 35    |
    | twentysixteen         | theme  | success | 1.3     | 0.5s  | 23    |
    +-----------------------+--------+---------+---------+-------+-------+

    # Use php-compat-cache to speed up scanning process
    $ git clone https://github.com/danielbachhuber/php-compat-cache.git ~/php-compat-cache
    $ WP_CLI_PHP_COMPAT_CACHE=~/php-compat-cache wp php-compat --path=danielbachhuber
    +-----------------------+--------+---------+---------+--------+-------+
    | name                  | type   | compat  | version | time   | files |
    +-----------------------+--------+---------+---------+--------+-------+
    | wordpress             | core   | success | 4.7.6   | cached |       |
    | akismet               | plugin | success | 3.2     | cached | 13    |
    | debug-bar             | plugin | success | 0.8.4   | 0.14s  | 10    |
    | oembed-gist           | plugin | success | 4.7.1   | 0.07s  | 1     |
    | danielbachhuber-theme | theme  | success | 0.0.0   | 0.36s  | 30    |
    | twentyfifteen         | theme  | success | 1.7     | cached | 22    |
    | twentyseventeen       | theme  | success | 1.1     | cached | 35    |
    | twentysixteen         | theme  | success | 1.3     | cached | 23    |
    +-----------------------+--------+---------+---------+--------+-------+

## Installing

Installing this package requires WP-CLI's latest stable release. Update to the latest stable release with `wp cli update`.

Once you've done so, you can install this package with:

    wp package install git@github.com:danielbachhuber/php-compat-command.git

## Contributing

We appreciate you taking the initiative to contribute to this project.

Contributing isn’t limited to just code. We encourage you to contribute in the way that best fits your abilities, by writing tutorials, giving a demo at your local meetup, helping other users with their support questions, or revising our documentation.

For a more thorough introduction, [check out WP-CLI's guide to contributing](https://make.wordpress.org/cli/handbook/contributing/). This package follows those policy and guidelines.

### Reporting a bug

Think you’ve found a bug? We’d love for you to help us get it fixed.

Before you create a new issue, you should [search existing issues](https://github.com/danielbachhuber/php-compat-command/issues?q=label%3Abug%20) to see if there’s an existing resolution to it, or if it’s already been fixed in a newer version.

Once you’ve done a bit of searching and discovered there isn’t an open or fixed issue for your bug, please [create a new issue](https://github.com/danielbachhuber/php-compat-command/issues/new). Include as much detail as you can, and clear steps to reproduce if possible. For more guidance, [review our bug report documentation](https://make.wordpress.org/cli/handbook/bug-reports/).

### Creating a pull request

Want to contribute a new feature? Please first [open a new issue](https://github.com/danielbachhuber/php-compat-command/issues/new) to discuss whether the feature is a good fit for the project.

Once you've decided to commit the time to seeing your pull request through, [please follow our guidelines for creating a pull request](https://make.wordpress.org/cli/handbook/pull-requests/) to make sure it's a pleasant experience. See "[Setting up](https://make.wordpress.org/cli/handbook/pull-requests/#setting-up)" for details specific to working on this package locally.

## Support

Github issues aren't for general support questions, but there are other venues you can try: http://wp-cli.org/#support


*This README.md is generated dynamically from the project's codebase using `wp scaffold package-readme` ([doc](https://github.com/wp-cli/scaffold-package-command#wp-scaffold-package-readme)). To suggest changes, please submit a pull request against the corresponding part of the codebase.*
