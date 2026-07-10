# Changelog

All notable changes to the FOSSBilling Order Analytics module will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.3] - 2026-07-11

### Added
- Added `year_to_date` (Year to Date) preset to date range filter.

### Changed
- Set default date range to `this_month` instead of `last_30_days`.
- Group chart data by month for periods longer than 90 days.
- Show actual date/month names in chart tooltips and legends instead of 'Current Period' / 'Previous Period'.

### Fixed
- Fixed previous period logic for `this_month`, `last_month` and `this_year` presets to shift exactly by 1 month or 1 year, ensuring accurate visual comparison.

## [1.0.2] - 2026-07-10

### Fixed
- Fixed release zip folder casing to `Orderanalytics` to match FOSSBilling requirements.
- Updated `minimum_fossbilling_version` to `0.8.3`.
- Updated manifest author details.

## [1.0.0] - 2026-07-10

### Added
- First Release.
