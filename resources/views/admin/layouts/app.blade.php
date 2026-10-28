<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>@yield('title', 'Admin Panel') | Investment Point</title>
@include('partials.favicon')
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
/* ===== Design tokens: one set of colours, radii and shadows for the whole admin panel ===== */
:root{
  --sidebar-width:256px;
  --navy:#0d1640;--navy-2:#16215a;--blue:#2b3f9e;--blue-2:#4a63d8;--blue-soft:#eef1fb;--gold:#f4bd32;
  --logo-blue:#2b3f9e;--logo-blue-soft:#eef1fb;
  --bg:#f4f6fb;--card:#fff;--line:#e6eaf2;--line-2:#f0f2f7;--ink:#0f1b3d;--text:#344161;--muted:#6b7895;
  --green:#0a9f63;--green-soft:#e6f7ef;--red:#dc3545;--red-soft:#fdecee;--amber:#b45309;--amber-soft:#fff6e0;
  --radius:14px;--radius-sm:10px;
  --shadow:0 1px 2px rgba(16,24,64,.04),0 6px 18px rgba(16,24,64,.05);
  --shadow-lg:0 12px 32px rgba(16,24,64,.12);
}
*{box-sizing:border-box}
body{margin:0;font-family:'Inter','Segoe UI',system-ui,-apple-system,Roboto,Arial,sans-serif;background:var(--bg);color:var(--text);min-height:100vh;-webkit-font-smoothing:antialiased;font-size:14px}
a{color:var(--blue)}
::selection{background:rgba(43,63,158,.18)}
:focus-visible{outline:3px solid rgba(43,63,158,.35);outline-offset:2px}

/* ===== SIDEBAR ===== */
.sidebar{position:fixed;top:0;left:0;width:var(--sidebar-width);height:100vh;background:linear-gradient(180deg,var(--navy) 0%,var(--navy-2) 100%);color:#b9c2e6;display:flex;flex-direction:column;z-index:1000;transition:transform .28s ease}
.sidebar-brand{display:flex;align-items:center;gap:12px;padding:20px 20px 18px;text-decoration:none;border-bottom:1px solid rgba(255,255,255,.07)}
.sidebar-brand .brand-mark{width:42px;height:42px;border-radius:12px;background:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 6px 16px rgba(0,0,0,.25)}
.sidebar-brand .brand-mark img{width:34px;height:auto}
.sidebar-brand b{display:block;color:#fff;font-size:15px;font-weight:800;letter-spacing:.1px;line-height:1.2}
.sidebar-brand small{display:block;color:var(--gold);font-size:13px;font-weight:800;letter-spacing:1.4px;text-transform:uppercase}
.sidebar-nav{flex:1;padding:14px 12px;overflow-y:auto;scrollbar-width:thin;scrollbar-color:rgba(255,255,255,.15) transparent}
.nav-label{display:block;padding:14px 12px 6px;font-size:10.5px;font-weight:700;letter-spacing:1.2px;text-transform:uppercase;color:#6f7bab}
.nav-label:first-child{padding-top:2px}
.sidebar-nav a{position:relative;display:flex;align-items:center;gap:11px;padding:9px 12px;margin-bottom:2px;color:#c3cbec;font-size:13.5px;font-weight:500;text-decoration:none;border-radius:var(--radius-sm);transition:background .15s,color .15s}
.sidebar-nav a i{font-size:16px;width:20px;text-align:center;color:#8590c2;transition:color .15s}
.sidebar-nav a:hover{background:rgba(255,255,255,.06);color:#fff}
.sidebar-nav a:hover i{color:#fff}
.sidebar-nav a.active{background:rgba(255,255,255,.1);color:#fff;font-weight:600}
.sidebar-nav a.active i{color:var(--gold)}
.sidebar-nav a.active::before{content:"";position:absolute;left:-12px;top:8px;bottom:8px;width:4px;border-radius:0 4px 4px 0;background:var(--gold)}
/* Badge (new subscriptions, unread questions...) on a link or a closed group */
.nav-badge{margin-left:auto;background:var(--gold);color:var(--navy);font-size:11px;font-weight:800;border-radius:20px;padding:1px 8px;line-height:1.5}
/* Folding groups: E-Paper, News & Market, ... */
.nav-group{margin:0 0 2px}
.nav-group summary{display:flex;align-items:center;gap:11px;padding:9px 12px;color:#c3cbec;font-size:13.5px;font-weight:500;border-radius:var(--radius-sm);cursor:pointer;list-style:none;user-select:none;transition:background .15s,color .15s}
.nav-group summary::-webkit-details-marker{display:none}
.nav-group summary i{font-size:16px;width:20px;text-align:center;color:#8590c2}
.nav-group summary:hover{background:rgba(255,255,255,.06);color:#fff}
.nav-group[open] summary{color:#fff}
.nav-group summary .nav-badge+.nav-chevron{margin-left:8px}
.nav-group .nav-chevron{margin-left:auto;font-size:11px!important;width:auto!important;transition:transform .2s}
.nav-group[open] .nav-chevron{transform:rotate(180deg)}
.nav-group[open] summary .nav-badge{display:none}
.nav-group-links{padding:2px 0 6px 12px;margin-left:21px;border-left:1px solid rgba(255,255,255,.1)}
.nav-group-links a{padding:7px 10px;font-size:13px}
.nav-group-links a i{font-size:14px}
.nav-group-links a.active::before{left:-13px}
.avatar{border-radius:50%;background:linear-gradient(135deg,var(--gold),#f8d77a);color:var(--navy);font-weight:800;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;letter-spacing:.3px}

/* ===== Bootstrap in the panel's colours ===== */
.btn{--bs-btn-font-weight:600;--bs-btn-border-radius:var(--radius-sm);--bs-btn-font-size:13.5px;--bs-btn-padding-y:.5rem;--bs-btn-padding-x:1rem}
.btn-sm{--bs-btn-font-size:12.5px;--bs-btn-padding-y:.35rem;--bs-btn-padding-x:.7rem;--bs-btn-border-radius:9px}
.btn-primary{--bs-btn-bg:var(--blue);--bs-btn-border-color:var(--blue);--bs-btn-hover-bg:#22327f;--bs-btn-hover-border-color:#22327f;--bs-btn-active-bg:#1b2a6b;--bs-btn-active-border-color:#1b2a6b;--bs-btn-disabled-bg:var(--blue);--bs-btn-disabled-border-color:var(--blue);box-shadow:0 4px 12px rgba(43,63,158,.22)}
.btn-outline-primary{--bs-btn-color:var(--blue);--bs-btn-border-color:#c9d1ef;--bs-btn-hover-bg:var(--blue);--bs-btn-hover-border-color:var(--blue);--bs-btn-active-bg:#22327f}
.btn-outline-secondary{--bs-btn-color:var(--text);--bs-btn-border-color:var(--line);--bs-btn-hover-bg:var(--blue-soft);--bs-btn-hover-color:var(--blue);--bs-btn-hover-border-color:#c9d1ef;--bs-btn-active-bg:var(--blue-soft);--bs-btn-active-color:var(--blue);background:#fff}
.btn-outline-danger{--bs-btn-border-color:#f2c4c9}
.btn-outline-success{--bs-btn-border-color:#bfe6d3}
.btn-outline-dark{--bs-btn-border-color:var(--line)}
.pagination{--bs-pagination-color:var(--blue);--bs-pagination-hover-color:#22327f;--bs-pagination-hover-bg:var(--blue-soft);--bs-pagination-focus-color:#22327f;--bs-pagination-focus-box-shadow:0 0 0 .2rem rgba(43,63,158,.2);--bs-pagination-active-bg:var(--blue);--bs-pagination-active-border-color:var(--blue);--bs-pagination-border-color:var(--line);--bs-pagination-font-size:13px;--bs-pagination-border-radius:9px;margin-bottom:0;flex-wrap:wrap;gap:4px}
.pagination .page-link{border-radius:9px!important;min-width:34px;text-align:center}
nav[aria-label] p.small.text-muted{font-size:13px;margin:0}
.form-control,.form-select{border-color:#d9dfeb;border-radius:var(--radius-sm);color:var(--ink)}
.form-control:focus,.form-select:focus{border-color:var(--blue);box-shadow:0 0 0 4px rgba(43,63,158,.12)}
.form-control::placeholder{color:#9aa4bd}
.form-check-input:checked{background-color:var(--blue);border-color:var(--blue)}
.form-label{font-size:12.5px;font-weight:600;color:var(--ink);margin-bottom:6px}
.alert{border-radius:12px;border-width:1px}
.text-muted{color:var(--muted)!important}
.modal-content{border:0;border-radius:16px!important;box-shadow:var(--shadow-lg)}

/* ===== MAIN AREA ===== */
.main-content{margin-left:var(--sidebar-width);min-height:100vh;display:flex;flex-direction:column}
.topbar{background:rgba(255,255,255,.88);backdrop-filter:saturate(1.6) blur(10px);-webkit-backdrop-filter:saturate(1.6) blur(10px);border-bottom:1px solid var(--line);padding:12px 28px;min-height:68px;display:flex;justify-content:space-between;align-items:center;gap:12px;position:sticky;top:0;z-index:99}
.topbar-left{display:flex;align-items:center;min-width:0;gap:12px}
.topbar-title{min-width:0}
.topbar h1{margin:0;font-size:19px;font-weight:700;color:var(--ink);letter-spacing:-.2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.topbar-sub{display:block;font-size:12px;color:var(--muted);margin-top:1px}
.topbar-right{display:flex;align-items:center;gap:10px;flex-shrink:0}
.topbar-btn{height:38px;padding:0 13px;border:1px solid var(--line);border-radius:var(--radius-sm);background:#fff;color:var(--text);font-size:13px;font-weight:600;display:inline-flex;align-items:center;gap:7px;text-decoration:none;cursor:pointer;transition:border-color .15s,color .15s,background .15s}
.topbar-btn:hover{border-color:#c9d1ef;color:var(--blue);background:var(--blue-soft)}
.topbar-btn.danger{color:var(--red)}
.topbar-btn.danger:hover{border-color:#f2c4c9;background:var(--red-soft);color:var(--red)}
.topbar-user{display:flex;align-items:center;gap:9px;padding:3px 12px 3px 3px;border:1px solid var(--line);border-radius:40px;text-decoration:none;color:var(--ink);font-size:13px;font-weight:600;background:#fff}
.topbar-user:hover{border-color:#c9d1ef;color:var(--blue)}
.topbar-user .avatar{width:32px;height:32px;font-size:12px}
.content{padding:28px;flex:1;width:100%;max-width:1440px}
.sidebar-toggle{display:none;border:1px solid var(--line);background:#fff;border-radius:var(--radius-sm);width:40px;height:40px;align-items:center;justify-content:center;font-size:20px;color:var(--ink);cursor:pointer;flex-shrink:0}
.sidebar-backdrop{display:none}

/* Testing-mode reminder: one slim line, details on click */
.test-banner{display:flex;align-items:flex-start;gap:10px;background:var(--amber-soft);border:1px solid #f6dfa5;color:#7a4b00;border-radius:12px;padding:10px 14px;margin-bottom:20px;font-size:13px}
.test-banner>i{color:#d48a00;font-size:16px;line-height:1.3}
.test-banner details summary{cursor:pointer;font-weight:600;list-style:none}
.test-banner details summary::-webkit-details-marker{display:none}
.test-banner details summary::after{content:" Show details";font-weight:500;text-decoration:underline;color:#9a6200;margin-left:4px}
.test-banner details[open] summary::after{content:" Hide"}
.test-banner details p{margin:6px 0 0;line-height:1.55}

/* ===== CARDS ===== */
.widget,.table-card,.form-card{background:var(--card);border-radius:var(--radius);box-shadow:var(--shadow);border:1px solid var(--line)}
.widget{padding:20px}
.widget .icon{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:21px;color:#fff;flex-shrink:0}
.widget h3{margin:0;font-size:26px;font-weight:800;color:var(--ink)}
.widget p{margin:4px 0 0;font-size:12.5px;color:var(--muted);font-weight:500}

/* ===== TABLES ===== */
.table-card{overflow-x:auto}
.table-card .table{margin-bottom:0;min-width:640px;--bs-table-bg:transparent}
.table-card .table th{background:#f8f9fc;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);padding:13px 12px;border-bottom:1px solid var(--line);vertical-align:middle}
.table-card .table td{padding:14px 12px;vertical-align:middle;font-size:13.5px;color:var(--text);border-bottom:1px solid var(--line-2)}
.table-card .table th:first-child,.table-card .table td:first-child{padding-left:18px}
.table-card .table th:last-child,.table-card .table td:last-child{padding-right:18px}
.table-card .table tbody tr:last-child td{border-bottom:0}
.table-card .table tbody tr{transition:background .12s}
.table-card .table tbody tr:hover{background:#fafbfe}
.table-card .table td strong{color:var(--ink);font-weight:600}
.table-card .table .btn-sm{min-width:32px;min-height:32px;padding:0 9px;display:inline-flex;align-items:center;justify-content:center;gap:4px}
.table-thumb{width:64px;height:46px;object-fit:cover;border-radius:8px;box-shadow:0 0 0 1px var(--line)}

/* Search / filter bars above the lists: controls keep their natural width on one line */
.content>.d-flex.justify-content-between form.d-flex .form-control,
.content>.d-flex.justify-content-between form.d-flex .form-select{width:auto}
.content>.d-flex.justify-content-between form.d-flex input[type=search]{min-width:220px}

/* ===== FORMS ===== */
.form-card{padding:26px}
.form-card label{font-size:12.5px;font-weight:600;color:var(--ink);margin-bottom:6px}
.form-card .form-control,.form-card .form-select{border-radius:var(--radius-sm);border:1px solid #d9dfeb;font-size:13.5px;padding:10px 14px}
.form-card .form-control:focus,.form-card .form-select:focus{border-color:var(--blue);box-shadow:0 0 0 4px rgba(43,63,158,.12)}
.form-card .btn{padding:.6rem 1.2rem}

/* ===== STATUS PILLS ===== */
.badge-active,.badge-inactive,.badge-pending{display:inline-flex;align-items:center;gap:5px;font-weight:600;font-size:11.5px;padding:4px 10px;border-radius:20px;white-space:nowrap}
.badge-active{background:var(--green-soft);color:#08744a}
.badge-inactive{background:var(--red-soft);color:#b42332}
.badge-pending{background:var(--amber-soft);color:var(--amber)}
.badge-active::before,.badge-inactive::before,.badge-pending::before{content:"";width:6px;height:6px;border-radius:50%;background:currentColor;flex-shrink:0}
.badge-active:has(> i)::before,.badge-inactive:has(> i)::before,.badge-pending:has(> i)::before{display:none}

/* Reader count under an article title in the admin lists */
.view-badge{display:inline-flex;align-items:center;gap:5px;margin-top:6px;padding:2px 9px;border-radius:20px;background:var(--blue-soft);color:var(--blue);font-size:11.5px;font-weight:600;white-space:nowrap}
.view-badge i{font-size:12px}
.view-badge.zero{background:#f1f3f8;color:#8a94ad}

/* Responsive: sidebar slides in from the left on tablets/phones */
@media(max-width:1199px){.topbar-user span:not(.avatar){display:none}.topbar-user{padding:3px}}
@media(max-width:991px){
  .sidebar{transform:translateX(-100%);box-shadow:none}
  .sidebar.open{transform:translateX(0);box-shadow:12px 0 40px rgba(0,0,0,.3)}
  .sidebar-backdrop.show{display:block;position:fixed;inset:0;background:rgba(13,22,64,.5);backdrop-filter:blur(2px);z-index:999}
  .main-content{margin-left:0}
  .sidebar-toggle{display:inline-flex}
  .content{padding:22px}
}
@media(max-width:768px){
  /* 16px fields: iPhones don't zoom the page in when one is tapped */
  .form-control,.form-select,.form-card .form-control,.form-card .form-select,input[type=search]{font-size:16px!important}
}
@media(max-width:600px){
  .topbar{padding:10px 14px;min-height:60px}
  .topbar h1{font-size:16.5px}
  .topbar-sub{display:none}
  .topbar-btn span{display:none}
  .topbar-btn{width:38px;padding:0;justify-content:center}
  .topbar-right{gap:8px}
  .content{padding:14px}
  .test-banner{margin-bottom:14px}
  .content>.d-flex.justify-content-between{flex-wrap:wrap;gap:10px}
  /* Search / filter bars: full width, the search box gets its own line when it would be squeezed */
  .content>.d-flex.justify-content-between>form,.content>.d-flex.justify-content-between>div.d-flex,
  .content>.d-flex.justify-content-between>div.d-flex>form:has(input[type=search]){flex:1 1 100%;flex-wrap:wrap}
  .content>.d-flex.justify-content-between form input[type=search]{min-width:0!important;width:auto!important;flex:1 1 140px}
  .content>.d-flex.justify-content-between form .form-select{flex:1 1 130px;width:auto!important;min-width:0}
  .form-card{padding:16px}
  .widget{padding:16px}
  .modal-footer{justify-content:stretch}
  .modal-footer>.btn{flex:1 1 auto}
}

/* Card view for tables: one card per row, each value with its column name above it.
   The script at the bottom adds .cards to a table's box on phones, and on tablets / small laptops when
   the table is too wide to fit (labels come from the table header). */
.cards{overflow:visible!important;background:transparent!important;border:0!important;box-shadow:none!important;border-radius:0!important}
.cards>.table{min-width:0!important;width:100%;background:transparent}
.cards>.table>tbody,.cards>.table>tbody>tr,.cards>.table>tbody>tr>td{display:block;width:100%}
.cards>.table>thead,.cards>.table>thead>tr{display:block;background:transparent}
.cards>.table>thead>tr>th{display:none}
/* "Select all" checkbox stays visible above the cards */
.cards>.table>thead>tr>th:has(> [data-select-all]){display:flex;align-items:center;gap:8px;background:transparent;border:0;padding:0 2px 10px!important;font-size:12.5px;text-transform:none;letter-spacing:0;color:var(--text)}
.cards>.table>thead>tr>th:has(> [data-select-all])::after{content:"Select all"}
.cards>.table>tbody>tr{position:relative;display:grid!important;grid-template-columns:minmax(0,1fr) minmax(0,1fr);column-gap:16px;background:#fff;border:1px solid var(--line);border-radius:var(--radius);box-shadow:var(--shadow);margin-bottom:12px;padding:4px 16px}
.cards>.table>tbody>tr:hover{background:#fff}
.cards>.table>tbody>tr>td{grid-column:1/-1;padding:10px 0!important;border-bottom:1px solid var(--line-2)!important;max-width:none!important;white-space:normal!important;overflow-wrap:anywhere;text-align:left!important}
.cards>.table>tbody>tr>td:last-child{border-bottom:0!important}
/* Short values (status, date, count...) sit two per line; the row number is not needed on a card */
.cards>.table>tbody>tr>td.td-half{grid-column:auto}
.cards>.table>tbody>tr>td.td-hide{display:none!important}
.cards>.table>tbody>tr>td[data-label]::before{content:attr(data-label);display:block;margin-bottom:4px;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#8a94ad}
/* Row checkbox: top-right corner of its card */
.cards>.table>tbody>tr>td:has(> [data-select-item]){position:absolute;top:12px;right:14px;width:auto;padding:0!important;border:0!important}
.cards>.table>tbody>tr>td:has(> [data-select-item])::before{display:none}
.cards>.table>tbody>tr:has([data-select-item])>td:nth-child(2){padding-right:30px!important}
/* "Nothing found" row */
.cards>.table>tbody>tr>td[colspan]{text-align:center!important}
.cards>.table>tbody>tr>td[colspan]::before{display:none}
.cards>.table .form-select{max-width:260px}
.cards .table-thumb{width:96px;height:64px}
/* Entry grids (Add Member Picks): "Stock 1" heading, then the short fields two per line */
.cards>.table-stack>tbody>tr>td:first-child{grid-column:1/-1;display:flex!important;align-items:baseline;gap:4px;padding-bottom:2px!important;border:0!important;font-size:13px;font-weight:800;color:var(--ink)!important}
.cards>.table-stack>tbody>tr>td:first-child::before{content:"Stock"!important;display:inline!important;margin:0!important;font-size:13px!important;text-transform:none!important;letter-spacing:0!important;color:var(--ink)!important}
.cards>.table-stack>tbody>tr>td:nth-child(n+3){grid-column:auto}
@media(min-width:768px){.cards>.table>tbody>tr{grid-template-columns:repeat(3,minmax(0,1fr))}}
</style>
{{-- Page-specific styles: pages add a full <style>…</style> block in @section('extra-css') --}}
@yield('extra-css')
</head>
<body>
@include('partials.page-progress')

{{-- SIDEBAR --}}
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>
<aside class="sidebar" id="adminSidebar">
  <a class="sidebar-brand" href="{{ \App\Support\AdminPages::home(Auth::user()) }}" aria-label="Investment Point admin panel">
    <span class="brand-mark"><img src="{{ asset('images/logo-bull.png') }}" alt=""></span>
    <small>Admin Panel</small>
  </a>
  @php
    $adminName = Auth::user()->name;
    $initials = collect(preg_split('/\s+/', trim($adminName)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') ?: 'A';
    $newSubscriptions = \App\Models\SubscriptionRequest::where('status', 'new')->count();
    $unreadQueries = \App\Models\ReaderQuery::whereNull('read_at')->count();
    $pendingBlogs = \App\Models\UserBlog::where('status', 'pending')->count();
    $newInsuranceEnquiries = \App\Models\InsuranceEnquiry::whereNull('read_at')->count();
    // E-Paper page open now: its paper (list / add: ?edition=, edit: the edition being edited)
    $paperRoute = request()->route('digitalNewspaper');
    $currentEdition = request()->routeIs('admin.digital-newspaper.*')
        ? (request('edition') ?: ($paperRoute instanceof \App\Models\DigitalNewspaper ? $paperRoute->edition : 'weekly')) : null;
    $link = fn ($route, $icon, $label, $pattern, $badge = 0, $params = []) => compact('route', 'icon', 'label', 'pattern', 'badge', 'params');
    // Used every day: always visible at the top
    $topLinks = [
      $link('admin.dashboard', 'bi-grid-1x2-fill', 'Dashboard', 'admin.dashboard'),
      $link('admin.subscriptions.index', 'bi-star-fill', 'Subscriptions', 'admin.subscriptions.*', $newSubscriptions),
      $link('admin.news.index', 'bi-graph-up-arrow', 'Market Trend', 'admin.news.*'),
      $link('admin.latest-news.index', 'bi-lightning-charge-fill', 'Latest News', 'admin.latest-news.*'),
      $link('admin.technical-analysis.index', 'bi-bar-chart-line-fill', 'Technical Analysis', 'admin.technical-analysis.*'),
      $link('admin.editor-articles.index', 'bi-pen-fill', "Editor's View", 'admin.editor-articles.*'),
      $link('admin.news-inbox.index', 'bi-inbox-fill', 'News Inbox', 'admin.news-inbox.*'),
    ];
    // Everything else, folded into groups (the group of the open page starts open)
    $groups = [
      ['label' => 'E-Paper', 'icon' => 'bi-newspaper', 'links' => collect(\App\Models\DigitalNewspaper::EDITIONS)->map(fn ($name, $key) =>
          $link('admin.digital-newspaper.index', $key === 'weekly' ? 'bi-journal-richtext' : 'bi-file-earmark-pdf', $name, $currentEdition === $key ? 'admin.digital-newspaper.*' : '-', 0, ['edition' => $key]))->values()->all()],
      ['label' => 'News & Market', 'icon' => 'bi-broadcast', 'links' => [
        $link('admin.popular-news.index', 'bi-fire', 'Most Popular News', 'admin.popular-news.*'),
        $link('admin.market-indices.index', 'bi-activity', 'Market Indices', 'admin.market-indices.*'),
        $link('admin.divya-bhaskar.index', 'bi-link-45deg', 'Divya Bhaskar', 'admin.divya-bhaskar.*'),
      ]],
      ['label' => 'Articles & Media', 'icon' => 'bi-collection-fill', 'links' => [
        $link('admin.media.index', 'bi-images', 'Media Library', 'admin.media.*'),
        $link('admin.expert-posts.index', 'bi-journal-richtext', "Expert's View", 'admin.expert-posts.*'),
        $link('admin.blogs.index', 'bi-journal-text', 'User Blogs', 'admin.blogs.*', $pendingBlogs),
        $link('admin.media-articles.index', 'bi-newspaper', 'Media Articles', 'admin.media-articles.*'),
        $link('admin.youtube.index', 'bi-youtube', 'YouTube Videos', 'admin.youtube.*'),
      ]],
      ['label' => 'Members', 'icon' => 'bi-people-fill', 'links' => [
        $link('admin.members.index', 'bi-person-badge', 'Members', 'admin.members.*'),
        $link('admin.member-picks.index', 'bi-bullseye', 'Member Picks', 'admin.member-picks.*'),
        $link('admin.premium.index', 'bi-gem', 'Premium Content', 'admin.premium.*'),
      ]],
      ['label' => 'Services', 'icon' => 'bi-shield-check', 'links' => [
        $link('admin.insurance-enquiries.index', 'bi-person-lines-fill', 'Insurance Enquiries', 'admin.insurance-enquiries.*', $newInsuranceEnquiries),
        $link('admin.insurance-products.index', 'bi-umbrella', 'Insurance Products', 'admin.insurance-products.*'),
      ]],
      ['label' => 'Settings', 'icon' => 'bi-gear-fill', 'links' => [
        $link('admin.settings.edit', 'bi-sliders', 'Site Settings', 'admin.settings.*'),
        $link('admin.team.users.index', 'bi-person-gear', 'User & Role Management', 'admin.team.users.*'),
        $link('admin.team.roles.index', 'bi-ui-checks-grid', 'Role & Permissions', 'admin.team.roles.*'),
        $link('admin.account.edit', 'bi-shield-lock-fill', 'My Account', 'admin.account.*'),
        $link('admin.legal-pages.index', 'bi-file-earmark-text-fill', 'Legal Pages', 'admin.legal-pages.*'),
        $link('admin.queries.index', 'bi-chat-left-text-fill', 'Disclaimer Questions', 'admin.queries.*', $unreadQueries),
      ]],
    ];
    // Only the pages this user may open (a role made in Users & Roles may give just a few)
    $me = Auth::user();
    // Team users without the Dashboard: the Welcome page instead, at the top
    if (!$me->canOpenAdminRoute('admin.dashboard')) {
      array_unshift($topLinks, $link('admin.welcome', 'bi-house-heart-fill', 'Welcome', 'admin.welcome'));
    }
    $topLinks = array_values(array_filter($topLinks, fn ($l) => $me->canOpenAdminRoute($l['route'])));
    $groups = array_values(array_filter(array_map(fn ($g) => ['links' => array_values(array_filter($g['links'], fn ($l) => $me->canOpenAdminRoute($l['route'])))] + $g, $groups), fn ($g) => $g['links']));
    $overview = ['admin.welcome', 'admin.dashboard', 'admin.subscriptions.index'];
  @endphp
  <nav class="sidebar-nav">
    @foreach($topLinks as $i => $l)
      @php $section = in_array($l['route'], $overview, true) ? 'Overview' : 'Publish'; @endphp
      @if($i === 0 || $section !== (in_array($topLinks[$i - 1]['route'], $overview, true) ? 'Overview' : 'Publish'))<span class="nav-label">{{ $section }}</span>@endif
      <a href="{{ route($l['route'], $l['params']) }}" @class(['active' => request()->routeIs($l['pattern'])])><i class="bi {{ $l['icon'] }}"></i> {{ $l['label'] }}@if($l['badge'])<span class="nav-badge">{{ $l['badge'] }}</span>@endif</a>
    @endforeach

    <span class="nav-label">Manage</span>
    @foreach($groups as $g)
      @php
        $open = collect($g['links'])->contains(fn ($l) => request()->routeIs($l['pattern']));
        $badge = collect($g['links'])->sum('badge');
      @endphp
      <details class="nav-group" @if($open) open @endif>
        <summary><i class="bi {{ $g['icon'] }}"></i> {{ $g['label'] }}@if($badge)<span class="nav-badge">{{ $badge }}</span>@endif<i class="bi bi-chevron-down nav-chevron"></i></summary>
        <div class="nav-group-links">
          @foreach($g['links'] as $l)
            <a href="{{ route($l['route'], $l['params']) }}" @class(['active' => request()->routeIs($l['pattern'])])><i class="bi {{ $l['icon'] }}"></i> {{ $l['label'] }}@if($l['badge'])<span class="nav-badge">{{ $l['badge'] }}</span>@endif</a>
          @endforeach
        </div>
      </details>
    @endforeach
  </nav>
</aside>

{{-- MAIN CONTENT --}}
<div class="main-content">
  <div class="topbar">
    <div class="topbar-left">
      <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="Open menu" aria-expanded="false"><i class="bi bi-list"></i></button>
      <div class="topbar-title">
        <h1>@yield('page-title', 'Dashboard')</h1>
        <span class="topbar-sub">{{ now()->timezone('Asia/Kolkata')->format('l, d F Y') }}</span>
      </div>
    </div>
    <div class="topbar-right">
      <a class="topbar-btn" href="{{ route('home') }}" target="_blank" rel="noopener" title="Open the website"><i class="bi bi-box-arrow-up-right"></i> <span>View website</span></a>
      <a class="topbar-user" href="{{ route('admin.account.edit') }}" title="My Account"><span class="avatar">{{ $initials }}</span> <span>{{ $adminName }}</span></a>
      <form action="{{ route('admin.logout') }}" method="POST" class="m-0">
        @csrf
        <button type="submit" class="topbar-btn danger" title="Logout"><i class="bi bi-power"></i> <span>Logout</span></button>
      </form>
    </div>
  </div>
  <div class="content">
    {{-- Flash Messages --}}
    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert" style="border-radius:10px;font-size:13px;font-weight:600">
      <i class="bi bi-check-circle-fill me-1"></i> {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif
    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert" style="border-radius:10px;font-size:13px;font-weight:600">
      <i class="bi bi-exclamation-triangle-fill me-1"></i> {{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif
    @if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert" style="border-radius:10px;font-size:13px">
      <ul class="mb-0">
        @foreach($errors->all() as $error)
        <li>{{ $error }}</li>
        @endforeach
      </ul>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    {{-- Safety reminder: test switches that must be off on the live site --}}
    @php($testSwitches = array_filter([app(\App\Services\Razorpay::class)->mode() === 'demo' ? 'free demo payments (SUBSCRIPTION_DEMO_PAYMENTS)' : null, app(\App\Services\Razorpay::class)->mode() === 'test' ? 'Razorpay TEST keys (no real money)' : null, app(\App\Services\LoginOtp::class)->mode() === 'demo' ? 'login OTP shown on screen (OTP_DEMO)' : null]))
    @if($testSwitches)
    <div class="test-banner" role="status">
      <i class="bi bi-cone-striped"></i>
      <details>
        <summary>Testing mode is on</summary>
        <p>{{ ucfirst(implode(', ', $testSwitches)) }}. Anyone could subscribe or log in as a member without paying. Turn these off in <code>.env</code> (and add the real keys) before the site goes live.</p>
      </details>
    </div>
    @endif

    @yield('content')
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Tablet/phone: ☰ opens the sidebar; tap the dimmed area or press Esc to close
(function () {
  var sidebar = document.getElementById('adminSidebar');
  var toggle = document.getElementById('sidebarToggle');
  var backdrop = document.getElementById('sidebarBackdrop');
  function setOpen(open) {
    sidebar.classList.toggle('open', open);
    backdrop.classList.toggle('show', open);
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  }
  toggle.addEventListener('click', function () { setOpen(!sidebar.classList.contains('open')); });
  backdrop.addEventListener('click', function () { setOpen(false); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setOpen(false); });
})();

// Phones: tables show as cards, so each cell gets its column name (shown above the value by CSS)
document.querySelectorAll('.table-card .table, .table-stack').forEach(function (table) {
  var heads = Array.from(table.querySelectorAll(':scope > thead > tr:first-child > th')).map(function (th) { return th.textContent.trim(); });
  table.querySelectorAll(':scope > tbody > tr').forEach(function (tr) {
    Array.from(tr.children).forEach(function (td, i) {
      if (!heads[i] || td.hasAttribute('colspan')) return;
      td.setAttribute('data-label', heads[i]);
      if (heads[i] === '#' && table.closest('.table-card')) td.classList.add('td-hide');
      else if (td.textContent.trim().length <= 24 && !td.querySelector('img, input:not([type=hidden]), select, textarea, br')) td.classList.add('td-half');
    });
  });
});

// Card view: always on phones; on tablets / small laptops only for tables too wide to fit
(function () {
  var boxes = Array.from(document.querySelectorAll('.table-card, .table-responsive')).filter(function (box) {
    return box.querySelector(':scope > .table');
  });
  if (!boxes.length) return;
  function layout() {
    boxes.forEach(function (box) {
      box.classList.remove('cards');
      if (window.innerWidth < 768 || (window.innerWidth < 1200 && box.scrollWidth > box.clientWidth + 1)) box.classList.add('cards');
    });
  }
  layout();
  var t;
  window.addEventListener('resize', function () { clearTimeout(t); t = setTimeout(layout, 120); });
  window.addEventListener('load', layout); // images and fonts can change table widths
})();

document.querySelectorAll('[data-bulk-form]').forEach(function (form) {
  var items = document.querySelectorAll('[form="' + form.id + '"][data-select-item]');
  var submit = form.querySelector('[data-bulk-submit]');
  var selectAll = document.querySelector('[data-select-all]');
  var updateState = function () {
    var selected = Array.from(items).filter(function (item) { return item.checked; });
    submit.disabled = selected.length === 0;
    if (selectAll) {
      selectAll.checked = items.length > 0 && selected.length === items.length;
      selectAll.indeterminate = selected.length > 0 && selected.length < items.length;
    }
  };
  items.forEach(function (item) { item.addEventListener('change', updateState); });
  if (selectAll) {
    selectAll.addEventListener('change', function () {
      items.forEach(function (item) { item.checked = selectAll.checked; });
      updateState();
    });
  }
  form.addEventListener('submit', function (event) {
    if (!confirm('Delete all selected items? This action cannot be undone.')) {
      event.preventDefault();
    }
  });
});
</script>

{{-- Rich text editor (TinyMCE 6, MIT licence, no API key) for every textarea.rich-editor --}}
<script>
(function () {
  var areas = document.querySelectorAll('textarea.rich-editor');
  if (!areas.length) return; // only load the editor on pages that have one

  // TinyMCE hides the textarea, so the browser's "required" check can't work; we check on submit instead
  areas.forEach(function (ta) {
    if (ta.hasAttribute('required')) { ta.dataset.required = '1'; ta.removeAttribute('required'); }
  });

  var script = document.createElement('script');
  script.src = 'https://cdn.jsdelivr.net/npm/tinymce@6.8.5/tinymce.min.js';
  script.onload = function () { areas.forEach(initEditor); };
  document.head.appendChild(script);

  function initEditor(ta) {
  tinymce.init({
    target: ta,
    // Taller box for full article content, shorter for short descriptions
    height: (ta.getAttribute('rows') | 0) >= 10 ? 520 : 300,
    promotion: false,
    branding: false,
    menubar: 'file edit insert view format table tools',
    plugins: 'advlist autolink lists link image charmap preview searchreplace visualblocks code fullscreen insertdatetime media table wordcount emoticons',
    toolbar: [
      'undo redo | code | removeformat | blocks | bold italic underline',
      'alignleft aligncenter alignright alignjustify | bullist numlist | outdent indent | link image',
      'preview media | forecolor backcolor | emoticons | table | fullscreen'
    ],
    toolbar_mode: 'wrap',
    // Paragraphs justified while writing, as the website shows them (layouts.app .np-justify)
    content_style: 'body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;font-size:15px;line-height:1.6}p,li{text-align:justify}'
      // Pages shown with their own website styles (legal pages): same colours and boxes as on the site
      + (ta.dataset.editorCss ? ':root{--navy:#141f55;--blue:#2b3f9e;--blue2:#eaeefb;--gold:#f4bd32;--text:#141f3d;--muted:#5f6b85;--line:#e3e7f2;--shadow:none}'
        + ta.dataset.editorCss + 'body.tc-doc{border:0;box-shadow:none;border-radius:0;padding:16px 20px;margin:0}' : ''),
    body_class: ta.dataset.editorBodyClass || '',
    link_default_target: '_blank',
    link_assume_external_targets: 'https',
    image_caption: false,
    image_dimensions: true,
    paste_data_images: false,      // pasted images would be stored inside the text; use Insert > Image with a URL
    convert_urls: false,
    relative_urls: false,
    browser_spellcheck: true,
    contextmenu: false
  });
  }

  // Save editor content into the textarea and enforce "required" before submitting
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form.querySelector('textarea.rich-editor') || !window.tinymce) return;
    tinymce.triggerSave();
    var missing = Array.from(form.querySelectorAll('textarea.rich-editor[data-required]')).find(function (ta) {
      var editor = tinymce.get(ta.id);
      return !(editor ? editor.getContent({ format: 'text' }).trim() : ta.value.trim());
    });
    if (missing) {
      e.preventDefault();
      var label = form.querySelector('label[for="' + missing.id + '"]');
      alert((label ? label.textContent.replace('*', '').trim() : 'This field') + ' is required.');
      var ed = tinymce.get(missing.id);
      if (ed) ed.focus();
    }
  }, true);
})();
</script>
{{-- "Generate with AI" for image uploads marked data-ai-image="16:9" (ratio of where the image shows on the site) --}}
<div class="modal fade" id="aiImageModal" tabindex="-1" aria-labelledby="aiImageTitle" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content" style="border-radius:14px">
      <div class="modal-header">
        <h5 class="modal-title" id="aiImageTitle" style="font-weight:800;font-size:16px"><i class="bi bi-stars text-warning me-1"></i> Generate image with AI</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <label class="form-label" for="aiPrompt">Describe the image</label>
        <textarea class="form-control" id="aiPrompt" rows="3" maxlength="1000" placeholder="e.g. Gold bars on a dark desk under soft studio light"></textarea>
        <small class="text-muted d-block mt-1" style="font-size:12px">Leave the headline as it is for an automatic real-photo scene, or describe one place or object yourself (no company names: they turn into fake logos).</small>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-2">
          <div class="form-check" style="font-size:13px">
            <input class="form-check-input" type="checkbox" id="aiStyle" checked>
            <label class="form-check-label" for="aiStyle">Realistic news photo (like Reuters / Bloomberg; no text, no logos)</label>
          </div>
          <small class="text-muted" id="aiSizeNote"></small>
        </div>
        <div class="mt-3 text-center" id="aiResult" style="background:#f3f5fb;border-radius:10px;min-height:140px;display:flex;align-items:center;justify-content:center;overflow:hidden">
          <span class="text-muted" style="font-size:13px">Your image will appear here</span>
        </div>
        <div class="text-muted d-none mt-2" id="aiCredit" style="font-size:12px"></div>
        <div class="alert alert-danger mt-3 mb-0 d-none" id="aiError" style="font-size:13px"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="border-radius:8px">Cancel</button>
        {{-- Real stock photo (Pixabay / Pexels, free, no daily AI allowance) - the most real-looking option --}}
        <button type="button" class="btn btn-outline-primary" id="aiPhoto" style="border-radius:8px;font-weight:600"><i class="bi bi-camera me-1"></i> Find real photo (free)</button>
        <button type="button" class="btn btn-primary" id="aiGenerate" style="border-radius:8px;font-weight:600"><i class="bi bi-stars me-1"></i> Generate with AI</button>
        <button type="button" class="btn btn-success d-none" id="aiUse" style="border-radius:8px;font-weight:600"><i class="bi bi-check-lg me-1"></i> Use this image</button>
      </div>
    </div>
  </div>
</div>
<script>
(function () {
  var inputs = document.querySelectorAll('input[type=file][data-ai-image]');
  if (!inputs.length || !window.bootstrap) return;

  var modalEl = document.getElementById('aiImageModal');
  var modal = new bootstrap.Modal(modalEl);
  var promptEl = document.getElementById('aiPrompt');
  var styleEl = document.getElementById('aiStyle');
  var resultEl = document.getElementById('aiResult');
  var errorEl = document.getElementById('aiError');
  var genBtn = document.getElementById('aiGenerate');
  var useBtn = document.getElementById('aiUse');
  var sizeNote = document.getElementById('aiSizeNote');
  var current = null;   // the file input being filled
  var canvas = null;    // the picture that goes into the form

  // Article pictures (16:9 fields): the whole picture, never cut - when the article is saved the website
  // fits it into its 16:9 frame (App\Support\ImageOptimizer::frame). Round profile pictures (1:1) are
  // cropped to a square around the middle, as before.
  function crop(img, ratio) {
    var parts = ratio.split(':').map(Number), r = parts[0] / parts[1];
    var w = img.naturalWidth, h = img.naturalHeight, sw = w, sh = h;
    if (ratio === '1:1') { if (w / h > r) sw = Math.round(h * r); else sh = Math.round(w / r); }
    var c = document.createElement('canvas');
    c.width = sw; c.height = sh;
    c.getContext('2d').drawImage(img, (w - sw) / 2, (h - sh) / 2, sw, sh, 0, 0, sw, sh);
    return c;
  }

  inputs.forEach(function (input) {
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn btn-sm btn-outline-primary mt-2';
    btn.style.cssText = 'border-radius:8px;font-weight:600;font-size:12px';
    btn.innerHTML = '<i class="bi bi-stars me-1"></i> Generate with AI';
    var preview = document.createElement('img');
    preview.className = 'd-none mt-2';
    preview.alt = 'AI image preview';
    preview.style.cssText = 'display:block;max-width:260px;border-radius:8px;border:1px solid #e2e9f0';
    input.insertAdjacentElement('afterend', preview);
    input.insertAdjacentElement('afterend', btn);
    input._aiPreview = preview;
    input._aiButton = btn; // the Media Library box below puts it next to its own button
    // Picking a normal file afterwards replaces the AI image, so hide its preview
    input.addEventListener('change', function () { if (!input._aiSetting) preview.classList.add('d-none'); });

    btn.addEventListener('click', function () {
      current = input;
      canvas = null;
      var from = document.getElementById(input.dataset.aiPromptFrom || 'title');
      var name = document.getElementById('name');
      var base = from && from.value.trim();
      if (input.dataset.aiImage === '1:1') {
        promptEl.value = 'Professional business portrait illustration of a ' + (base || 'stock market analyst') + ', friendly expression, plain soft background';
      } else if (!promptEl.value || promptEl.dataset.auto === '1') {
        // The headline itself: the server turns it into a real-looking photo scene (not an "illustration")
        promptEl.value = base || '';
      }
      promptEl.dataset.auto = '1';
      sizeNote.textContent = 'Shape: ' + input.dataset.aiImage + (input.dataset.aiImage === '1:1' ? ' (round photo)' : '')
        + (input.dataset.aiLook === 'bull-bear' ? ' - Market Trend style: painted bull (or bear) with the NSE building' : '');
      resultEl.innerHTML = '<span class="text-muted" style="font-size:13px">Your image will appear here</span>';
      errorEl.classList.add('d-none');
      useBtn.classList.add('d-none');
      creditEl.classList.add('d-none');
      genBtn.innerHTML = '<i class="bi bi-stars me-1"></i> Generate with AI';
      photoBtn.innerHTML = '<i class="bi bi-camera me-1"></i> Find real photo (free)';
      modal.show();
      setTimeout(function () { promptEl.focus(); }, 300);
    });
  });
  promptEl.addEventListener('input', function () { promptEl.dataset.auto = '0'; });

  // Both buttons (real Pexels photo / AI image) load a picture into the preview the same way
  function loadPicture(btn, url, body, labels) {
    var prompt = promptEl.value.trim();
    if (prompt.length < 2) { promptEl.focus(); return; }
    genBtn.disabled = photoBtn.disabled = true; useBtn.classList.add('d-none'); errorEl.classList.add('d-none');
    creditEl.classList.add('d-none');
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> ' + labels.busy;
    resultEl.innerHTML = '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading</span></div>';

    fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) },
      body: JSON.stringify(Object.assign({ prompt: prompt, from_headline: promptEl.dataset.auto === '1' }, body))
    })
    .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
    .then(function (r) {
      if (!r.ok || !r.data.image) throw new Error((r.data && r.data.message) || 'No picture this time. Please try again.');
      return new Promise(function (resolve, reject) {
        var img = new Image();
        img.onload = function () { resolve({ img: img, data: r.data }); };
        img.onerror = function () { reject(new Error('The picture could not be read. Try again.')); };
        img.src = r.data.image;
      });
    })
    .then(function (res) {
      canvas = crop(res.img, current.dataset.aiImage);
      canvas.style.cssText = 'max-width:100%;max-height:360px;border-radius:8px';
      resultEl.innerHTML = '';
      resultEl.appendChild(canvas);
      if (res.data.credit) {
        creditEl.innerHTML = 'Photo by <a target="_blank" rel="noopener"></a> on <span></span> · search: <i></i>';
        creditEl.querySelector('span').textContent = res.data.provider_name || 'Pixabay';
        creditEl.querySelector('a').textContent = res.data.credit;
        creditEl.querySelector('a').href = res.data.credit_url;
        creditEl.querySelector('i').textContent = res.data.query || '';
        creditEl.classList.remove('d-none');
      }
      useBtn.classList.remove('d-none');
      btn.innerHTML = labels.again;
    })
    .catch(function (err) {
      errorEl.textContent = err.message;
      errorEl.classList.remove('d-none');
      resultEl.innerHTML = '<span class="text-muted" style="font-size:13px">No image</span>';
      btn.innerHTML = labels.idle;
    })
    .finally(function () { genBtn.disabled = photoBtn.disabled = false; });
  }

  var photoBtn = document.getElementById('aiPhoto');
  var creditEl = document.getElementById('aiCredit');
  photoBtn.addEventListener('click', function () {
    loadPicture(photoBtn, @json(route('admin.stock-photo')), {}, {
      busy: 'Finding a real photo…',
      again: '<i class="bi bi-arrow-repeat me-1"></i> Another real photo',
      idle: '<i class="bi bi-camera me-1"></i> Find real photo (free)'
    });
  });
  genBtn.addEventListener('click', function () {
    loadPicture(genBtn, @json(route('admin.ai-image')), { style: styleEl.checked, ratio: current.dataset.aiImage, look: current.dataset.aiLook || null }, {
      busy: 'Generating… (about 10 sec)',
      again: '<i class="bi bi-arrow-repeat me-1"></i> Generate again',
      idle: '<i class="bi bi-stars me-1"></i> Try again'
    });
  });

  // Put the picture into the normal file input, so "Save" uploads it like any photo
  useBtn.addEventListener('click', function () {
    if (!canvas || !current) return;
    canvas.toBlob(function (blob) {
      var file = new File([blob], 'ai-image-' + Date.now() + '.jpg', { type: 'image/jpeg' });
      var dt = new DataTransfer();
      dt.items.add(file);
      current.files = dt.files;
      current._aiSetting = true;
      current.dispatchEvent(new Event('change', { bubbles: true }));
      current._aiSetting = false;
      current._aiPreview.src = URL.createObjectURL(blob);
      current._aiPreview.classList.remove('d-none');
      modal.hide();
    }, 'image/jpeg', 0.9);
  });
})();
</script>
{{-- MEDIA LIBRARY picker (like WordPress): a "Media Library" button next to every image "Choose File" --}}
<style>
.media-drop{display:flex;flex-direction:column;align-items:center;gap:6px;padding:30px 16px;border:2px dashed #c9d2e6;border-radius:12px;background:#f8f9fd;text-align:center;font-size:13.5px;transition:background .15s,border-color .15s}
.media-drop>i{font-size:34px;color:var(--blue-2);line-height:1}
.media-drop.over{background:var(--blue-soft);border-color:var(--blue)}
.media-drop-status{width:100%;max-width:560px;font-size:12.5px}
.media-drop-status .progress{height:6px;margin-top:6px}
/* A file refused because its name is already in the library: the picture that has the name + what to do */
.media-dup{display:flex;align-items:center;gap:12px;margin-top:10px;padding:12px 14px;border:1px solid #f3c98b;border-left:4px solid #f59e0b;border-radius:10px;background:#fff8eb;text-align:left}
.media-dup img{width:58px;height:58px;object-fit:cover;border-radius:8px;flex-shrink:0;box-shadow:0 0 0 1px #f3d9ad;background:#fff}
.media-dup .dup-text{flex:1;min-width:0;font-size:12.5px;line-height:1.45;color:#6b4a12}
.media-dup .dup-text b{display:flex;align-items:center;gap:6px;font-size:13px;color:#92400e;margin-bottom:2px}
.media-dup .dup-text em{font-style:normal;font-weight:700;color:#3d2a06;word-break:break-word}
.media-dup .btn{flex-shrink:0;border-radius:8px;font-size:12px;font-weight:700;white-space:nowrap}
.media-drop-status .media-err{display:flex;align-items:center;gap:6px;margin-top:8px;padding:8px 12px;border-radius:8px;background:var(--red-soft);color:var(--red);text-align:left}
.media-drop-status .dup-close{display:block;margin:8px auto 0;border:0;background:none;color:var(--muted);font-size:12px;font-weight:600}
.media-drop-status .dup-close:hover{color:var(--ink);text-decoration:underline}
@media(max-width:575.98px){.media-dup{flex-wrap:wrap}.media-dup .btn{width:100%}}
.mp-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(118px,1fr));gap:12px 10px;align-items:start}
/* A tile: the picture (square) and its name under it */
.mp-tile{display:flex;flex-direction:column;gap:6px;border:0;padding:0;background:none;cursor:pointer;text-align:left;min-width:0}
.mp-thumb{position:relative;display:block;width:100%;aspect-ratio:1;border-radius:8px;overflow:hidden;background:#eef1f7;box-shadow:0 0 0 1px var(--line);transition:box-shadow .15s}
.mp-thumb img{width:100%;height:100%;object-fit:cover;display:block}
.mp-name{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;font-size:12px;font-weight:600;line-height:1.35;color:var(--text);word-break:break-word}
.mp-tile:hover .mp-thumb{box-shadow:0 0 0 2px var(--blue-2)}
.mp-tile.selected .mp-thumb{box-shadow:0 0 0 3px var(--blue)}
.mp-tile.selected .mp-name{color:var(--blue)}
.mp-tile.selected .mp-thumb::after{content:"\F26E";font-family:bootstrap-icons;position:absolute;top:6px;right:6px;width:22px;height:22px;border-radius:50%;background:var(--blue);color:#fff;font-size:15px;line-height:22px;text-align:center;box-shadow:0 0 0 2px #fff}
#mediaPicker .nav-tabs .nav-link{font-size:13.5px;font-weight:600;color:var(--muted)}
#mediaPicker .nav-tabs .nav-link.active{color:var(--ink)}
.mp-chosen{display:flex;align-items:center;gap:10px;min-width:0;font-size:12.5px}
.mp-chosen img{width:44px;height:44px;object-fit:cover;border-radius:6px;box-shadow:0 0 0 1px var(--line)}
.mp-chosen b{display:block;max-width:260px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--ink)}
@media(max-width:575.98px){.mp-grid{grid-template-columns:repeat(3,1fr);gap:6px}.mp-chosen b{max-width:140px}}
/* An image field: chosen picture + Media Library / Generate with AI */
.img-field{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px 16px;padding:14px 16px;border:1.5px dashed #c9d2e6;border-radius:12px;background:#f8f9fd;transition:background .15s,border-color .15s}
.img-field.over{background:var(--blue-soft);border-color:var(--blue)}
.img-field.invalid{border-color:var(--red);background:var(--red-soft)}
.img-field-empty{display:flex;align-items:center;gap:12px;flex:1 1 340px;min-width:0;font-size:13px;color:var(--muted)}
.img-field-empty>i{flex-shrink:0;width:46px;height:46px;border-radius:10px;background:#fff;box-shadow:0 0 0 1px var(--line);display:flex;align-items:center;justify-content:center;font-size:20px;color:#9aa6c4}
.img-field-chosen{display:flex;align-items:center;gap:12px;flex:1 1 340px;min-width:0}
.img-field-chosen img{width:120px;height:68px;object-fit:cover;border-radius:8px;box-shadow:0 0 0 1px var(--line);background:#fff;flex-shrink:0}
.img-field-chosen .info{min-width:0;font-size:13px}
.img-field-chosen .info b{display:block;color:var(--ink);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.img-field-chosen .info small{color:var(--green);font-weight:600;font-size:12px}
.img-field-chosen .remove{margin-left:auto;flex-shrink:0;border-radius:8px;border:1px solid var(--line)}
.img-field-actions{display:flex;flex-wrap:wrap;gap:8px}
.img-field-actions .btn{border-radius:8px;font-weight:600;font-size:13px;padding:.45rem .9rem}
.img-field+small,.img-field~small.text-muted{display:block;margin-top:6px}
</style>
<div class="modal fade" id="mediaPicker" tabindex="-1" aria-labelledby="mediaPickerTitle" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable modal-fullscreen-md-down">
    <div class="modal-content" style="border-radius:14px">
      <div class="modal-header pb-0" style="flex-wrap:wrap;border-bottom:1px solid var(--line)">
        <h5 class="modal-title" id="mediaPickerTitle" style="font-weight:800;font-size:16px"><i class="bi bi-images me-1" style="color:var(--blue)"></i> Select a picture</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        <ul class="nav nav-tabs w-100 mt-2 border-0" role="tablist">
          <li class="nav-item" role="presentation"><button class="nav-link active" id="mpTabLibrary" data-bs-toggle="tab" data-bs-target="#mpLibrary" type="button" role="tab" aria-controls="mpLibrary" aria-selected="true"><i class="bi bi-images me-1"></i> Media Library</button></li>
          <li class="nav-item" role="presentation"><button class="nav-link" id="mpTabUpload" data-bs-toggle="tab" data-bs-target="#mpUpload" type="button" role="tab" aria-controls="mpUpload" aria-selected="false"><i class="bi bi-cloud-arrow-up me-1"></i> Upload Files</button></li>
        </ul>
      </div>
      <div class="modal-body tab-content" style="min-height:50vh">
        <div class="tab-pane fade" id="mpUpload" role="tabpanel" aria-labelledby="mpTabUpload">
          @include('admin.media._dropzone', ['id' => 'pickerDrop'])
        </div>
        <div class="tab-pane fade show active" id="mpLibrary" role="tabpanel" aria-labelledby="mpTabLibrary">
          <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <input type="search" class="form-control form-control-sm" id="mpSearch" placeholder="Search pictures by name…" aria-label="Search pictures" style="max-width:300px;border-radius:8px">
            <select class="form-select form-select-sm" id="mpSort" aria-label="Sort pictures" style="width:auto;border-radius:8px;font-weight:600;color:var(--ink)">
              @foreach(\App\Http\Controllers\Admin\MediaController::SORTS as $key => $label)
              <option value="{{ $key }}">{{ $label }}</option>
              @endforeach
            </select>
            <a href="{{ route('admin.media.index') }}" target="_blank" rel="noopener" class="ms-auto text-decoration-none" style="font-size:12.5px"><i class="bi bi-box-arrow-up-right me-1"></i>Manage Media Library</a>
          </div>
          <div class="mp-grid" id="mpGrid" role="listbox" aria-label="Pictures"></div>
          <p class="text-muted text-center my-4 d-none" id="mpEmpty" style="font-size:13px"></p>
          <div class="text-center mt-3"><button type="button" class="btn btn-sm btn-outline-secondary d-none" id="mpMore" style="border-radius:8px">Load more</button></div>
        </div>
      </div>
      <div class="modal-footer justify-content-between">
        <div class="mp-chosen" id="mpChosen"></div>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="border-radius:8px">Cancel</button>
          <button type="button" class="btn btn-primary" id="mpUse" disabled style="border-radius:8px;font-weight:600"><i class="bi bi-check-lg me-1"></i> Use this picture</button>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
(function () {
  var csrf = @json(csrf_token());
  var uploadUrl = @json(route('admin.media.store'));
  var listUrl = @json(route('admin.media.index'));

  // Uploader for every drop area: one picture per request (so a big batch never hits the server's size limit)
  document.querySelectorAll('[data-media-drop]').forEach(function (drop) {
    var fileInput = drop.querySelector('input[type=file]');
    var status = drop.querySelector('.media-drop-status');
    var busy = false;

    function uploadOne(file) {
      return new Promise(function (resolve, reject) {
        var fd = new FormData();
        fd.append('files[]', file);
        var xhr = new XMLHttpRequest();
        xhr.open('POST', uploadUrl);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-CSRF-TOKEN', csrf);
        xhr.upload.onprogress = function (e) { if (e.lengthComputable) status.querySelector('.progress-bar').style.width = Math.round(e.loaded / e.total * 100) + '%'; };
        xhr.onload = function () {
          var data = {};
          try { data = JSON.parse(xhr.responseText); } catch (e) {}
          if (xhr.status >= 200 && xhr.status < 300 && data.data) return resolve(data.data);
          var msg = data.errors ? Object.values(data.errors)[0][0] : (xhr.status === 413 ? 'This picture is too big for the server.' : (data.message || 'Upload failed.'));
          // A name already in the library: the picture that has it comes with the answer
          reject({ message: data.duplicate ? msg : file.name + ': ' + msg, duplicate: data.duplicate || null, file: file.name });
        };
        xhr.onerror = function () { reject({ message: file.name + ': no connection, please try again.' }); };
        xhr.send(fd);
      });
    }

    function upload(files) {
      files = Array.from(files).filter(function (f) { return /^image\//.test(f.type); });
      if (!files.length || busy) return;
      busy = true;
      var added = [], errors = [], i = 0;
      (function next() {
        if (i >= files.length) {
          busy = false;
          status.innerHTML = '';
          if (added.length) {
            var ok = document.createElement('div');
            ok.className = 'text-success fw-semibold';
            ok.textContent = added.length + ' ' + (added.length === 1 ? 'picture' : 'pictures') + ' uploaded.';
            status.appendChild(ok);
          }
          errors.forEach(function (err) {
            if (!err.duplicate) {
              var d = document.createElement('div');
              d.className = 'media-err';
              d.innerHTML = '<i class="bi bi-x-circle-fill"></i><span></span>';
              d.querySelector('span').textContent = err.message;
              return status.appendChild(d);
            }
            // Same name: a warning card with the picture that is already there
            var c = document.createElement('div');
            c.className = 'media-dup';
            c.innerHTML = '<img alt="">' +
              '<div class="dup-text"><b><i class="bi bi-exclamation-triangle-fill"></i> Not uploaded: this name is already in the Media Library</b>' +
              '<em></em> is already there. Rename the file and upload it again, or use the picture that is already there.</div>' +
              '<button type="button" class="btn btn-outline-warning btn-sm"></button>';
            c.querySelector('img').src = err.duplicate.src;
            c.querySelector('img').alt = err.duplicate.name;
            c.querySelector('em').textContent = '"' + err.duplicate.name + '"';
            var b = c.querySelector('button');
            b.innerHTML = drop.id === 'pickerDrop' ? '<i class="bi bi-check-lg me-1"></i>Use this one' : '<i class="bi bi-eye me-1"></i>Show it';
            b.addEventListener('click', function () { drop.dispatchEvent(new CustomEvent('media:show-existing', { detail: err.duplicate })); });
            status.appendChild(c);
          });
          if (errors.length) {
            var x = document.createElement('button');
            x.type = 'button';
            x.className = 'dup-close';
            x.textContent = 'OK, close this message';
            x.addEventListener('click', function () { status.innerHTML = ''; drop.dispatchEvent(new CustomEvent('media:dismissed')); });
            status.appendChild(x);
          }
          added.refused = errors.length;
          if (added.length) drop.dispatchEvent(new CustomEvent('media:uploaded', { detail: added }));
          return;
        }
        var file = files[i++];
        // One progress line for the batch (the last batch's messages are cleared when it starts)
        var bar = status.querySelector('.media-drop-progress') || document.createElement('div');
        bar.className = 'media-drop-progress';
        bar.innerHTML = '<div></div><div class="progress"><div class="progress-bar" style="width:0%"></div></div>';
        bar.firstChild.textContent = 'Uploading ' + i + ' of ' + files.length + ': ' + file.name;
        if (!bar.parentNode) { status.innerHTML = ''; status.appendChild(bar); }
        uploadOne(file).then(function (items) { added = added.concat(items); }, function (err) { errors.push(err); }).then(next);
      })();
    }

    fileInput.addEventListener('change', function () { upload(fileInput.files); fileInput.value = ''; });
    ['dragenter', 'dragover'].forEach(function (t) { drop.addEventListener(t, function (e) { e.preventDefault(); drop.classList.add('over'); }); });
    ['dragleave', 'drop'].forEach(function (t) { drop.addEventListener(t, function (e) { e.preventDefault(); if (t === 'drop' || !drop.contains(e.relatedTarget)) drop.classList.remove('over'); }); });
    drop.addEventListener('drop', function (e) { upload(e.dataTransfer.files); });
  });

  // The picker: every image "Choose File" in the admin panel gets a "Media Library" button
  var inputs = document.querySelectorAll('input[type=file][accept*="image"]:not([data-media-skip])');
  if (!inputs.length || !window.bootstrap) return;

  var modalEl = document.getElementById('mediaPicker');
  var modal = new bootstrap.Modal(modalEl);
  var grid = document.getElementById('mpGrid');
  var empty = document.getElementById('mpEmpty');
  var more = document.getElementById('mpMore');
  var search = document.getElementById('mpSearch');
  var sortEl = document.getElementById('mpSort');
  var useBtn = document.getElementById('mpUse');
  var chosenEl = document.getElementById('mpChosen');
  var current = null;   // the file input being filled
  var chosen = null;    // the picture selected in the grid
  var nextPage = 1, query = '', loading = false, loaded = false, request = 0, pending = null;

  function tile(item) {
    var b = document.createElement('button');
    b.type = 'button';
    b.className = 'mp-tile';
    b.setAttribute('role', 'option');
    b.title = item.name + (item.width ? ' (' + item.width + ' × ' + item.height + ')' : '');
    b.innerHTML = '<span class="mp-thumb"><img alt="" loading="lazy"></span><span class="mp-name"></span>';
    b.querySelector('img').src = item.src;
    b.querySelector('.mp-name').textContent = item.name;
    b.addEventListener('click', function () { select(item, b); });
    b.addEventListener('dblclick', function () { select(item, b); useBtn.click(); });
    grid.appendChild(b);
    return b;
  }

  function select(item, b) {
    chosen = item;
    grid.querySelectorAll('.mp-tile.selected').forEach(function (t) { t.classList.remove('selected'); t.setAttribute('aria-selected', 'false'); });
    b.classList.add('selected');
    b.setAttribute('aria-selected', 'true');
    chosenEl.innerHTML = '<img alt=""><div><b></b><span class="text-muted"></span></div>';
    chosenEl.querySelector('img').src = item.src;
    chosenEl.querySelector('b').textContent = item.name;
    chosenEl.querySelector('span').textContent = (item.width ? item.width + ' × ' + item.height + ' px · ' : '') + item.size;
    useBtn.disabled = false;
  }

  function load(reset) {
    if (loading && !reset) return;
    if (reset) { grid.innerHTML = ''; nextPage = 1; }
    loading = true;
    var mine = ++request;
    more.classList.add('d-none');
    empty.classList.remove('d-none');
    empty.textContent = 'Loading…';
    fetch(listUrl + '?page=' + nextPage + '&q=' + encodeURIComponent(query) + '&sort=' + encodeURIComponent(sortEl.value), { headers: { 'Accept': 'application/json' } })
      .then(function (res) { if (!res.ok) throw new Error(); return res.json(); })
      .then(function (data) {
        if (mine !== request) return;
        data.data.forEach(function (item) {
          var b = tile(item);
          if (item.id === pending) { select(item, b); pending = null; }
        });
        nextPage = data.next;
        more.classList.toggle('d-none', !data.next);
        empty.classList.toggle('d-none', grid.children.length > 0);
        empty.textContent = query ? 'No picture matches "' + query + '".' : 'No pictures yet. Click "Upload Files" above to add your pictures.';
        loaded = true;
      })
      .catch(function () { if (mine === request) empty.textContent = 'The pictures could not be loaded. Please try again.'; })
      .finally(function () { if (mine === request) loading = false; });
  }
  more.addEventListener('click', function () { load(false); });
  var t;
  search.addEventListener('input', function () { clearTimeout(t); t = setTimeout(function () { query = search.value.trim(); load(true); }, 300); });
  sortEl.addEventListener('change', function () { load(true); });

  // Uploaded in the picker: back to the library (newest first) with the first new picture selected.
  // Not when some files were refused (same name): their warning stays on the Upload tab to be read
  var pickerDrop = document.getElementById('pickerDrop');
  function showInLibrary(id, name) {
    pending = id;
    search.value = query = name;
    if (!name) sortEl.value = 'newest';
    load(true);
    bootstrap.Tab.getOrCreateInstance(document.getElementById('mpTabLibrary')).show();
  }
  pickerDrop.addEventListener('media:uploaded', function (e) {
    if (e.detail.refused) { pending = e.detail[0].id; search.value = query = ''; sortEl.value = 'newest'; load(true); return; }
    showInLibrary(e.detail[0].id, '');
  });
  // "Use this one" on a same-name warning: the picture already in the library, selected
  pickerDrop.addEventListener('media:show-existing', function (e) {
    pickerDrop.querySelector('.media-drop-status').innerHTML = '';
    showInLibrary(e.detail.id, e.detail.name);
  });

  // Each image field becomes one box: the chosen picture + "Media Library" / "Generate with AI" buttons.
  // The browser's own "Choose File" is hidden (uploading is done in the Media Library window); it still
  // holds the picture, so saving the form works exactly as before.
  inputs.forEach(function (input) {
    var box = document.createElement('div');
    box.className = 'img-field' + (input.classList.contains('is-invalid') ? ' invalid' : '');
    box.innerHTML =
      '<div class="img-field-empty"><i class="bi bi-image"></i><span>No new picture chosen. Pick one from the Media Library' +
        (input._aiButton ? ', generate one with AI' : '') + ' or drop a picture here.</span></div>' +
      '<div class="img-field-chosen d-none"><img alt="Chosen picture"><div class="info"><b></b><small>New picture · saved when you save</small></div>' +
        '<button type="button" class="btn btn-sm btn-light remove" title="Remove this picture" aria-label="Remove this picture"><i class="bi bi-x-lg"></i></button></div>' +
      '<div class="img-field-actions"><button type="button" class="btn btn-primary btn-sm media"><i class="bi bi-images me-1"></i> Media Library</button></div>';
    input.insertAdjacentElement('afterend', box);
    input.hidden = true;
    var actions = box.querySelector('.img-field-actions');
    if (input._aiButton) {
      input._aiButton.className = 'btn btn-outline-primary btn-sm';
      input._aiButton.style.cssText = '';
      actions.appendChild(input._aiButton);
    }
    // One preview for every source (Media Library, AI, a dropped file): whatever the input now holds
    if (input._aiPreview) input._aiPreview.remove();
    var empty = box.querySelector('.img-field-empty');
    var chosenBox = box.querySelector('.img-field-chosen');
    var img = chosenBox.querySelector('img');
    input.addEventListener('change', function () {
      var file = input.files && input.files[0];
      if (img.src) URL.revokeObjectURL(img.src);
      if (file) {
        img.src = URL.createObjectURL(file);
        chosenBox.querySelector('b').textContent = file.name;
        box.classList.remove('invalid');
      } else {
        img.removeAttribute('src');
      }
      chosenBox.classList.toggle('d-none', !file);
      empty.classList.toggle('d-none', !!file);
    });
    chosenBox.querySelector('.remove').addEventListener('click', function () {
      input.value = '';
      input.dispatchEvent(new Event('change', { bubbles: true }));
    });

    box.querySelector('.media').addEventListener('click', function () {
      current = input;
      bootstrap.Tab.getOrCreateInstance(document.getElementById('mpTabLibrary')).show();
      if (!loaded) load(true);
      modal.show();
    });

    // A picture dragged from the computer onto the box is used directly
    ['dragenter', 'dragover'].forEach(function (t) { box.addEventListener(t, function (e) { e.preventDefault(); box.classList.add('over'); }); });
    ['dragleave', 'drop'].forEach(function (t) { box.addEventListener(t, function (e) { e.preventDefault(); if (t === 'drop' || !box.contains(e.relatedTarget)) box.classList.remove('over'); }); });
    box.addEventListener('drop', function (e) {
      var file = Array.from(e.dataTransfer.files).find(function (f) { return /^image\/(jpeg|png|webp)$/.test(f.type); });
      if (!file) return;
      var dt = new DataTransfer();
      dt.items.add(file);
      input.files = dt.files;
      input.dispatchEvent(new Event('change', { bubbles: true }));
    });

    // A hidden input can't show the browser's "required" message, so check it here (e.g. the site logo)
    if (input.required && input.form) {
      input.required = false;
      input.form.addEventListener('submit', function (e) {
        if (input.files && input.files.length) return;
        e.preventDefault();
        box.classList.add('invalid');
        box.scrollIntoView({ behavior: 'smooth', block: 'center' });
        alert('Please choose a picture first (Media Library button).');
      });
    }
  });

  // Put the chosen picture into the form's normal file input, so "Save" uploads it like any photo
  useBtn.addEventListener('click', function () {
    if (!chosen || !current) return;
    var label = useBtn.innerHTML;
    useBtn.disabled = true;
    useBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Adding…';
    fetch(chosen.src)
      .then(function (res) { if (!res.ok) throw new Error(); return res.blob(); })
      .then(function (blob) {
        var ext = { 'image/png': 'png', 'image/webp': 'webp' }[blob.type] || 'jpg';
        var file = new File([blob], (chosen.name.replace(/[^\w\- ]+/g, '').trim() || 'image') + '.' + ext, { type: blob.type || 'image/jpeg' });
        var dt = new DataTransfer();
        dt.items.add(file);
        current.files = dt.files;
        current._aiSetting = true; // already web-sized: skip the in-browser shrink
        current.dispatchEvent(new Event('change', { bubbles: true }));
        current._aiSetting = false;
        // Which Media Library picture it was (its name): forms can use it (Technical Analysis: title + AI draft)
        current.dispatchEvent(new CustomEvent('media:picked', { bubbles: true, detail: chosen }));
        modal.hide();
      })
      .catch(function () { alert('The picture could not be loaded. Please try again.'); })
      .finally(function () { useBtn.disabled = false; useBtn.innerHTML = label; });
  });
})();
</script>
<script>
// FASTER PAGES: shrink big photos in the browser before they are uploaded (max 1600 px, good-quality JPEG),
// so the website never serves a 3-5 MB camera photo. Not for the site logo (keeps transparency) or
// newspaper clippings (#image_file: need full size to stay readable).
(function () {
  const MAX = 1600;
  document.querySelectorAll('input[type=file][accept*="image"]:not(#logo):not(#image_file):not([data-media-skip])').forEach(function (input) {
    input.addEventListener('change', function () {
      const file = input.files && input.files[0];
      if (!file || input._aiSetting || input._shrinking || !/^image\/(jpeg|png|webp)$/.test(file.type)) return;
      const img = new Image();
      const url = URL.createObjectURL(file);
      img.onload = function () {
        URL.revokeObjectURL(url);
        const scale = Math.min(1, MAX / Math.max(img.naturalWidth, img.naturalHeight));
        if (scale === 1 && file.size < 400 * 1024) return; // already small
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(img.naturalWidth * scale);
        canvas.height = Math.round(img.naturalHeight * scale);
        canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
        const png = file.type === 'image/png'; // PNGs may be transparent: keep them PNG
        canvas.toBlob(function (blob) {
          if (!blob || blob.size >= file.size) return; // not smaller: keep the original
          const name = file.name.replace(/\.\w+$/, '') + (png ? '.png' : '.jpg');
          const dt = new DataTransfer();
          dt.items.add(new File([blob], name, { type: blob.type }));
          input._shrinking = true;
          input.files = dt.files;
          input.dispatchEvent(new Event('change', { bubbles: true }));
          input._shrinking = false;
          let note = input.parentElement.querySelector('.shrink-note');
          if (!note) { note = document.createElement('small'); note.className = 'shrink-note text-success d-block mt-1'; input.insertAdjacentElement('afterend', note); }
          note.textContent = 'Photo optimised for the website: ' + Math.round(file.size / 1024) + ' KB → ' + Math.round(blob.size / 1024) + ' KB (' + canvas.width + '×' + canvas.height + ')';
        }, png ? 'image/png' : 'image/jpeg', 0.85);
      };
      img.src = url;
    });
  });
})();
</script>
@yield('scripts')
@php($deniedRoutes = \App\Support\AdminPages::deniedForScript(Auth::user()))
@if($deniedRoutes)
<script>
// A role user: hide the links, buttons and forms their role does not allow (a tidier screen only:
// the server refuses those requests anyway)
(function () {
  var denied = @json($deniedRoutes).map(function (r) { return { m: r.m, re: new RegExp(r.p) }; });
  function blocked(method, url) {
    var path;
    try { path = new URL(url, location.href).pathname; } catch (e) { return false; }
    return denied.some(function (r) { return r.m.indexOf(method) !== -1 && r.re.test(path); });
  }
  function sweep() {
    document.querySelectorAll('a[href]:not([data-rbac])').forEach(function (a) {
      var href = a.getAttribute('href');
      if (href && href.charAt(0) !== '#' && blocked('GET', href)) { a.dataset.rbac = '1'; a.style.display = 'none'; }
    });
    document.querySelectorAll('form[action]:not([data-rbac])').forEach(function (f) {
      var spoof = f.querySelector('input[name="_method"]');
      var method = ((spoof && spoof.value) || f.getAttribute('method') || 'GET').toUpperCase();
      if (!blocked(method, f.getAttribute('action'))) return;
      f.dataset.rbac = '1'; f.style.display = 'none';
      if (f.id) document.querySelectorAll('[form="' + f.id + '"]').forEach(function (el) { el.style.display = 'none'; });
    });
  }
  sweep();
  var timer;
  new MutationObserver(function () { clearTimeout(timer); timer = setTimeout(sweep, 60); }).observe(document.body, { childList: true, subtree: true });
})();
</script>
@endif
</body>
</html>
