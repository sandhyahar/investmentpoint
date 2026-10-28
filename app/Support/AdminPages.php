<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * The admin-panel pages and actions (View / Create / Edit / Delete) a role can be given in
 * Role & Permission Management. The Super Admin (users.role = "admin") opens everything; a "staff" user
 * only what is ticked for their (active) role, plus My Account. User & Role Management and Role &
 * Permission Management are Super Admin only and can never be given to a role (no self-promotion).
 *
 * A route belongs to a page by its name (admin.news.*), and its action comes from the last part of the
 * name: index / show = View, create / store = Create, edit / update = Edit, destroy / bulk-delete = Delete.
 * Pages added to the site later are open to the Super Admin only until they are listed here.
 */
class AdminPages
{
    public const ACTIONS = ['view' => 'View', 'create' => 'Create', 'edit' => 'Edit', 'delete' => 'Delete'];

    /** key => [label, sidebar group, main route, actions it has, route names it covers] */
    public const PAGES = [
        'dashboard' => ['Dashboard', 'Overview', 'admin.dashboard', ['view'], ['admin.dashboard']],
        'subscriptions' => ['Subscriptions', 'Overview', 'admin.subscriptions.index', ['view', 'edit', 'delete'], ['admin.subscriptions.*']],
        'news' => ['Market Trend', 'Publish', 'admin.news.index', ['view', 'create', 'edit', 'delete'], ['admin.news.*', 'admin.share-link.update']],
        'latest-news' => ['Latest News', 'Publish', 'admin.latest-news.index', ['view', 'create', 'edit', 'delete'], ['admin.latest-news.*']],
        'technical-analysis' => ['Technical Analysis', 'Publish', 'admin.technical-analysis.index', ['view', 'create', 'edit', 'delete'], ['admin.technical-analysis.*', 'admin.share-link.update']],
        'editor-articles' => ["Editor's View", 'Publish', 'admin.editor-articles.index', ['view', 'create', 'edit', 'delete'], ['admin.editor-articles.*']],
        'news-inbox' => ['News Inbox', 'Publish', 'admin.news-inbox.index', ['view', 'create', 'delete'], ['admin.news-inbox.*']],
        'digital-newspaper' => ['E-Paper (all papers)', 'E-Paper', 'admin.digital-newspaper.index', ['view', 'create', 'edit', 'delete'], ['admin.digital-newspaper.*']],
        'popular-news' => ['Most Popular News', 'News & Market', 'admin.popular-news.index', ['view', 'create', 'edit', 'delete'], ['admin.popular-news.*']],
        'market-indices' => ['Market Indices', 'News & Market', 'admin.market-indices.index', ['view', 'create', 'edit', 'delete'], ['admin.market-indices.*']],
        'divya-bhaskar' => ['Divya Bhaskar', 'News & Market', 'admin.divya-bhaskar.index', ['view', 'create', 'edit', 'delete'], ['admin.divya-bhaskar.*']],
        'media' => ['Media Library', 'Articles & Media', 'admin.media.index', ['view', 'create', 'edit', 'delete'], ['admin.media.*']],
        'expert-posts' => ["Expert's View", 'Articles & Media', 'admin.expert-posts.index', ['view', 'create', 'edit', 'delete'], ['admin.expert-posts.*']],
        'blogs' => ['User Blogs', 'Articles & Media', 'admin.blogs.index', ['view', 'edit', 'delete'], ['admin.blogs.*']],
        'media-articles' => ['Media Articles', 'Articles & Media', 'admin.media-articles.index', ['view', 'create', 'edit', 'delete'], ['admin.media-articles.*']],
        'youtube' => ['YouTube Videos', 'Articles & Media', 'admin.youtube.index', ['view', 'create', 'edit', 'delete'], ['admin.youtube.*']],
        'members' => ['Members', 'Members', 'admin.members.index', ['view', 'create', 'edit', 'delete'], ['admin.members.*']],
        'member-picks' => ['Member Picks', 'Members', 'admin.member-picks.index', ['view', 'create', 'edit', 'delete'], ['admin.member-picks.*']],
        'premium' => ['Premium Content', 'Members', 'admin.premium.index', ['view', 'create', 'edit', 'delete'], ['admin.premium.*']],
        'insurance-enquiries' => ['Insurance Enquiries', 'Services', 'admin.insurance-enquiries.index', ['view', 'delete'], ['admin.insurance-enquiries.*']],
        'insurance-products' => ['Insurance Products', 'Services', 'admin.insurance-products.index', ['view', 'create', 'edit', 'delete'], ['admin.insurance-products.*']],
        'settings' => ['Site Settings', 'Settings', 'admin.settings.edit', ['view', 'edit'], ['admin.settings.*']],
        'legal-pages' => ['Legal Pages', 'Settings', 'admin.legal-pages.index', ['view', 'edit'], ['admin.legal-pages.*']],
        'queries' => ['Disclaimer Questions', 'Settings', 'admin.queries.index', ['view', 'delete'], ['admin.queries.*']],
    ];

    /** Open to every signed-in admin-panel user: their own account, and the Welcome page */
    private const ALWAYS = ['admin.account.*', 'admin.welcome'];

    /** Helper buttons inside the forms (AI picture, titles, translation, AI writer): for users who may create or edit something */
    private const TOOLS = ['admin.ai-image', 'admin.stock-photo', 'admin.title-suggestions', 'admin.translate', 'admin.news-writer'];

    /** The Media Library picker inside the forms (list + upload, as JSON) works without the Media Library page */
    private const PICKER = ['admin.media.index', 'admin.media.store'];

    /** Every permission key ("news.create"), in the table's order */
    public static function allPermissions(): array
    {
        $keys = [];
        foreach (self::PAGES as $page => $p) {
            foreach ($p[3] as $action) {
                $keys[] = "{$page}.{$action}";
            }
        }

        return $keys;
    }

    /**
     * Only real permission keys, in the table's order. Create / Edit / Delete need View (the list page),
     * so View is added for any page that has another action ticked.
     */
    public static function clean(array $keys): array
    {
        foreach ($keys as $key) {
            $page = Str::before((string) $key, '.');
            if (isset(self::PAGES[$page])) {
                $keys[] = "{$page}.view";
            }
        }

        return array_values(array_intersect(self::allPermissions(), $keys));
    }

    /** The action(s) a route of a page needs: any one of them is enough */
    public static function actionsFor(string $route, string $mainRoute): array
    {
        if ($route === $mainRoute) {
            return ['view'];
        }

        return match (Str::afterLast($route, '.')) {
            'index', 'show', 'download', 'refresh', 'logos' => ['view'],
            'create', 'store', 'auto-publish', 'bulk-store', 'bulk-check' => ['create'],
            'write' => ['create', 'edit'], // AI writer on the add and the edit form
            'destroy', 'bulk-delete', 'dismiss', 'bulk-dismiss' => ['delete'],
            default => ['edit'], // edit, update, settings saves, resend, reset, share link
        };
    }

    /** Can this user use the admin route with this name? ($json: a request from the page's own script) */
    public static function allows(User $user, string $route, bool $json = false): bool
    {
        if ($user->isAdmin()) {
            return true;
        }
        if (!$user->isStaff()) {
            return false;
        }
        if (Str::is(self::ALWAYS, $route)) {
            return true;
        }

        $permissions = $user->adminPermissions();
        $writes = (bool) array_filter($permissions, fn ($p) => !str_ends_with($p, '.view'));
        if ($writes && (in_array($route, self::TOOLS, true) || ($json && in_array($route, self::PICKER, true)))) {
            return true;
        }

        foreach (self::PAGES as $page => [, , $main, , $patterns]) {
            if (!Str::is($patterns, $route)) {
                continue;
            }
            foreach (self::actionsFor($route, $main) as $action) {
                if (in_array("{$page}.{$action}", $permissions, true)) {
                    return true;
                }
            }
        }

        return false; // anything not listed (User & Role Management, new pages) is for the Super Admin only
    }

    /**
     * Where the user lands after login: the dashboard, else the Welcome page (hello, their role and username, and
     * buttons to their pages), never straight into one of their pages (owner, 23 Oct 2026).
     */
    public static function home(User $user): string
    {
        return route(self::allows($user, 'admin.dashboard') ? 'admin.dashboard' : 'admin.welcome');
    }

    /** The pages this user may open, for the Welcome page: [label, group, route] */
    public static function pagesFor(User $user): array
    {
        $pages = [];
        foreach (self::PAGES as $p) {
            if (self::allows($user, $p[2])) {
                $pages[] = ['label' => $p[0], 'group' => $p[1], 'route' => $p[2]];
            }
        }

        return $pages;
    }

    /**
     * The admin addresses this user may NOT use, for the page script that hides their links, buttons and
     * forms (a tidier screen only: the server refuses them anyway). [{m: methods, p: path regex}]
     */
    public static function deniedForScript(User $user): array
    {
        if ($user->isAdmin()) {
            return [];
        }
        $denied = [];
        foreach (Route::getRoutes()->getRoutesByName() as $name => $route) {
            if (!str_starts_with($name, 'admin.') || in_array($name, ['admin.login', 'admin.login.submit', 'admin.logout'], true) || self::allows($user, $name)) {
                continue;
            }
            $uri = preg_replace('/\{[^}]+\}/', '__P__', '/' . ltrim($route->uri(), '/'));
            $denied[] = [
                'm' => array_values(array_diff($route->methods(), ['HEAD'])),
                'p' => '^' . str_replace('__P__', '[^/]+', preg_quote($uri)) . '/?$',
            ];
        }

        return $denied;
    }
}
