<?php

namespace App\Support;

/**
 * Where in the sidebar the page we are on belongs.
 *
 * The sidebar only lists the overview pages (checklist.index and friends).
 * Comparing the current URL to those links exactly meant that every page
 * below an overview - a detail view, a settings page, a create form - counted
 * as "nowhere": the group collapsed and nothing was highlighted, so on
 * /kunde/checklists/settings you could no longer tell where you were.
 *
 * A link therefore also counts for the pages below it. The boundary is a
 * whole path segment, so /kunde/network does not swallow /kunde/network-map.
 * It is still a plain prefix test, which is why single top-level entries
 * (x-aside.toplink) keep comparing exactly - the dashboard sits at /kunde and
 * would otherwise be the active entry on every page of the customer.
 */
class NavigationState
{
    /** Does this link lead to the page we are on, or to a page below it? */
    public static function isActive(string $href): bool
    {
        $current = rtrim(parse_url(request()->url(), PHP_URL_PATH) ?: '', '/');
        $link = rtrim((string) parse_url($href, PHP_URL_PATH), '/');

        if ($link === '') {
            return false;
        }

        return $current === $link || str_starts_with($current, $link.'/');
    }

    /**
     * Does any link of a group lead to the page we are on?
     *
     * The links arrive as already rendered HTML (the component's slot), so the
     * hrefs are read back out of it instead of being passed along a second time.
     */
    public static function groupIsOpen(string $renderedLinks): bool
    {
        preg_match_all('/href="([^"]*)"/', $renderedLinks, $matches);

        foreach ($matches[1] as $href) {
            if (self::isActive(html_entity_decode($href, ENT_QUOTES | ENT_HTML5))) {
                return true;
            }
        }

        return false;
    }
}
