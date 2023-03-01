![Latest version](https://img.shields.io/github/v/release/magicsunday/webtrees-descendants-chart?sort=semver)
![License](https://img.shields.io/github/license/magicsunday/webtrees-descendants-chart)
![PHPStan](https://github.com/magicsunday/webtrees-descendants-chart/actions/workflows/phpstan.yml/badge.svg)
![PHPCodeSniffer](https://github.com/magicsunday/webtrees-descendants-chart/actions/workflows/phpcs.yml/badge.svg)
![CodeQL](https://github.com/magicsunday/webtrees-descendants-chart/actions/workflows/codeql-analysis.yml/badge.svg)

![Code Climate](https://codeclimate.com/github/magicsunday/webtrees-descendants-chart/badges/gpa.svg)
![Issue Count](https://codeclimate.com/github/magicsunday/webtrees-descendants-chart/badges/issue_count.svg)


<!-- TOC -->
* [Installation](#installation)
  * [Using Composer](#using-composer)
  * [Using Git](#using-git)
  * [Manual installation](#manual-installation)
* [Enable module](#enable-module)
* [Usage](#usage)
* [Development](#development)
  * [Run tests](#run-tests)
<!-- TOC -->


# Descendants chart
This module provides an SVG descendant chart for the [webtrees](https://www.webtrees.net) genealogical application. 
It is able to display up to 25 generations of descendants of an individual.

**But beware, if you select too many generations, it may take a while and even slow down your system significantly.**

In addition to the descendants, the respective spouses are also displayed for a person. The display can be 
deactivated via the configuration form so that only the direct descendants are displayed.

![descendants-chart-4-generations](assets/descendants-chart-4-generations.png)

*Fig. 1: A four generations descendants chart (drawn top to bottom)*


## Installation
Requires webtrees 2.2.

### Using Composer
To install using [composer](https://getcomposer.org/), just run the following command from the command line 
at the root directory of your webtrees installation.

``` 
composer require magicsunday/webtrees-descendants-chart
```

The module will automatically install into the ``modules_v4`` directory of your webtrees installation. 
To make this possible, the "magicsunday/webtrees-module-base" package is used. Approval within Composer
may be required here to authorize the execution of the package.

To remove the module run:
```
composer remove magicsunday/webtrees-descendants-chart
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

*Fig. 2: Control panel - Module administration*


## Usage
At the charts' menu, you will find a new link called `Descendants chart`. Use the provided configuration options
to adjust the layout of the charts according to your needs.

Furthermore, it is possible to export the generated tree diagram as an SVG or PNG image
in order to be able to use it elsewhere.


## Development
To build/update the javascript, run the following commands:

```
nvm install node
npm install
npm run prepare
```

### Run tests
```
composer update
vendor/bin/phpstan analyse -c phpstan.neon
vendor/bin/phpcs src/ --standard=PSR12
```
