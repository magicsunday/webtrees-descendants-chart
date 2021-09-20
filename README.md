[![License: GPL v3](https://img.shields.io/badge/License-GPL%20v3-blue.svg)](http://www.gnu.org/licenses/gpl-3.0)

[![PHPStan](https://img.shields.io/badge/PHPStan-level%205-brightgreen.svg?style=flat)](https://github.com/phpstan/phpstan)
[![PHP_CodeSniffer](https://img.shields.io/badge/PHP_CodeSniffer-PSR12-brightgreen.svg?style=flat)](https://github.com/squizlabs/PHP_CodeSniffer)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/magicsunday/webtrees-descendants-chart/badges/quality-score.png?b=main)](https://scrutinizer-ci.com/g/magicsunday/webtrees-descendants-chart/?branch=main)
[![Build Status](https://scrutinizer-ci.com/g/magicsunday/webtrees-descendants-chart/badges/build.png?b=main)](https://scrutinizer-ci.com/g/magicsunday/webtrees-descendants-chart/build-status/main)
[![Code Climate](https://codeclimate.com/github/magicsunday/webtrees-descendants-chart/badges/gpa.svg)](https://codeclimate.com/github/magicsunday/webtrees-descendants-chart)
[![Issue Count](https://codeclimate.com/github/magicsunday/webtrees-descendants-chart/badges/issue_count.svg)](https://codeclimate.com/github/magicsunday/webtrees-descendants-chart)

# Descendants chart
This module provides an SVG descendants chart for the [webtrees](https://www.webtrees.net) genealogy application. It
is capable to display up to 25 descendants generations of an individual.

![descendants-chart-5-generations](assets/descendants-chart-6-generations.png)
*Fig. 1: A six generations descendants chart (drawn top to bottom)*

**Caution: If you are rendering a lot of generations it may take a while and even slow down your system.**


## Installation
Requires webtrees 2.0.

### Using Composer
To install using [composer](https://getcomposer.org/), just run the following command from the command line 
at the root directory of your webtrees installation.

``` 
composer require magicsunday/webtrees-descendants-chart --update-no-dev
```

The module will automatically install into the ``modules_v4`` directory of your webtrees installation.

To remove the module run:
```
composer remove magicsunday/webtrees-descendants-chart --update-no-dev
```

### Using Git
If you are using ``git``, you could also clone the current main branch directly into your ``modules_v4`` directory 
by calling:

```
git clone https://github.com/magicsunday/webtrees-descendants-chart.git modules_v4/webtrees-descendants-chart
```

### Manual installation
To manually install the module, perform the following steps:

1. Download the [latest release](https://github.com/magicsunday/webtrees-descendants-chart/releases/latest).
2. Upload the downloaded file to your web server.
3. Unzip the package into your ``modules_v4`` directory.
4. Rename the folder to ``webtrees-descendants-chart``

## Enable module
Go to the control panel (admin section) of your installation and scroll down to the ``Modules`` section. Click 
on ``Charts`` (in subsection Genealogy). Enable the ``Descendants chart`` custom module (optionally disable the original
installed descendants chart module) and save your settings.

![Control panel - Module administration](assets/control-panel-modules.png)


## Usage
At the charts' menu, you will find a new link called `Descendants chart`. Use the provided configuration options
to adjust the layout of the charts according to your needs.

Right-clicking on an individual opens a tooltip providing more detailed information of the current individual.


## Development
To build/update the javascript, run the following commands:

```
npm install --unsafe-perm --save-dev
npm run prepare
```

### Run tests
```
composer update
vendor/bin/phpstan analyse -c phpstan.neon
vendor/bin/phpcs src/ --standard=PSR12
```
