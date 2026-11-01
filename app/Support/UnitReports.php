<?php

namespace App\Support;

/**
 * Unit reports (CEO / executive view): one unit and one section per page.
 * A Unit filter switches between Poultry, Goats, Rabbits, Dogs and Garden; section tabs switch
 * between Production, Feeds, Health & treatment and Deaths, keeping the dates.
 */
class UnitReports
{
    public const UNITS = ['poultry' => 'Poultry', 'goats' => 'Goats', 'rabbits' => 'Rabbits', 'dogs' => 'Dogs', 'garden' => 'Garden'];

    public const SECTIONS = ['production' => 'Production', 'feed' => 'Feeds', 'health' => 'Health & treatment', 'mortality' => 'Deaths'];

    /** Poultry report views for each section. */
    private const POULTRY_VIEWS = ['production' => 'eggs', 'feed' => 'feed', 'health' => 'health', 'mortality' => 'mortality'];

    public static function url(string $unit, ?string $section = null, array $dates = []): string
    {
        $dates = array_filter($dates);
        $section = array_key_exists((string) $section, self::SECTIONS) ? $section : 'production';
        if ($unit === 'poultry') {
            return route('poultry.report.index', ['view' => self::POULTRY_VIEWS[$section]] + $dates);
        }
        if ($unit === 'garden') {
            return route('operations.units.report', ['unit' => 'garden'] + $dates);
        }

        return route('operations.units.report', ['unit' => $unit, 'section' => $section] + $dates);
    }

    /** The section a poultry report view belongs to. */
    public static function sectionForPoultryView(string $view): ?string
    {
        return array_search($view, self::POULTRY_VIEWS, true) ?: null;
    }
}
