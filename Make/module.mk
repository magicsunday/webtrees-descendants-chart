# =============================================================================
# Module parameters
#
# The only file of the Make setup and of tests/Release/ that holds values of one
# module. Everything else is copied from the shared template by the sync script of
# the coding standard package and is the same in every module.
#
# The Makefile reads this file before every other fragment, and the release tests
# read it at run time. A line is an assignment with = or := (optionally indented
# with spaces). The last one of a name wins. ?= sets a name only when no line
# before it did. A value carries no comment after it, and += is not used.
# =============================================================================

# The package name of composer.json without the vendor. The release archive, the
# POT header and the issue URL of the translations carry it, and the name of the
# JavaScript bundle is derived from it by dropping the webtrees- prefix.
MODULE_NAME := webtrees-descendants-chart

# The segment of the PHP namespace between MagicSunday\Webtrees\ and the Test\ or
# the src/ part, as autoload and autoload-dev of composer.json spell it.
MODULE_NS := DescendantsChart

# The prefix injected into the bundled module-base classes at dist time. It keeps
# two modules at different versions from registering the same shared namespace.
# Must be unique per module.
SCOPE_NS := DescendantsChartVendor

# The webtrees constraint that release-prepare and release-bump write to composer.json.
WEBTREES_CONSTRAINT := ~2.2.0

# The file of the chart library build that marks a finished build. release-bootstrap
# runs npm ci when it is missing from node_modules/@magicsunday/webtrees-chart-lib/dist/.
CHART_LIB_MARKER := webtrees-chart-lib-infra.es.js

# The shared module-base views that the archive bundles, by file name without the
# extension.
SHARED_VIEWS := detail-panel help-popover toolbar-style
