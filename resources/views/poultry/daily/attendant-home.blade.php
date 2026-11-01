@extends('layouts.app')

@section('title', 'My Work')

@section('content')
@push('styles')
<style>
    .content:has(.attendant-home){max-width:none;padding:24px clamp(16px,4vw,60px) 50px;background:#faf8f4}
    .attendant-home{--worker-red:#a8272d;--worker-ink:#171b22;--worker-muted:#66717d;--worker-line:#eee9e1;display:grid;gap:18px;color:var(--worker-ink);font-family:inherit;container-type:inline-size}
    @media(min-width:900px){.shell--plain:has(.attendant-home){max-width:none;width:100%}.shell--plain:has(.attendant-home) .content{padding:26px clamp(24px,4vw,60px) 56px}.shell--plain:has(.attendant-home) .topbar-home::after{content:'Brooks AgroFarm';margin-left:12px;color:#171b22;font-size:1.05rem;font-weight:800;white-space:nowrap}.attendant-action{min-height:190px;grid-template-columns:112px minmax(0,1fr) 46px;padding:20px 26px}.attendant-action__icon{width:112px;height:112px}.attendant-action__icon svg{width:58px;height:58px}}
    .attendant-home *{box-sizing:border-box}
    .attendant-hero{position:relative;min-height:208px;display:flex;align-items:center;overflow:hidden;padding:28px 4%;border:1px solid #f2e5d5;border-left:5px solid #e99b43;border-radius:18px;background:linear-gradient(100deg,#fffaf4 0%,#fffaf4 46%,#fff1dd 100%)}
    .attendant-hero__copy{position:relative;z-index:1;max-width:620px}.attendant-hero__eyebrow{margin:0 0 8px;color:var(--worker-red);font-size:.83rem;font-weight:850;letter-spacing:.14em;text-transform:uppercase}.attendant-hero h2{margin:0;color:#141920;font-size:clamp(1.8rem,3vw,2.45rem);line-height:1.12;letter-spacing:-.035em}.attendant-hero p:last-child{margin:8px 0 0;color:#5e6975;font-size:1rem}
    .attendant-hero__art{position:absolute;right:0;bottom:0;width:min(55%,690px);height:100%;opacity:.97;pointer-events:none}.attendant-panel{border:1px solid var(--worker-line);border-radius:17px;background:#fff;box-shadow:0 4px 14px rgba(35,30,22,.035)}
    .attendant-house{padding:17px 22px 20px}.attendant-house h3{margin:0 0 12px;font-size:1.16rem;letter-spacing:-.02em}.attendant-house form{max-width:min(100%,920px)}.attendant-house__select{display:flex;align-items:center;gap:18px;width:100%;min-height:72px;padding:12px 20px;border:1px solid #dedfe2;border-radius:12px;background:#fff;color:#151a21}.attendant-house__select svg{width:34px;height:34px;flex:0 0 34px;color:var(--worker-red)}.attendant-house__select select{width:100%;border:0;outline:0;background:transparent;color:inherit;font-family:inherit;font-size:1.08rem;font-weight:600;cursor:pointer}.attendant-house__hint{margin:9px 0 0;color:var(--worker-muted);font-size:.82rem}
    .attendant-actions{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.attendant-action{min-height:160px;display:grid;grid-template-columns:96px minmax(0,1fr) 46px;align-items:center;gap:16px;padding:17px 22px;border:1px solid var(--worker-line);border-radius:17px;color:inherit;text-decoration:none;box-shadow:0 3px 12px rgba(35,30,22,.025);transition:transform .16s,box-shadow .16s}.attendant-action:hover{transform:translateY(-2px);box-shadow:0 9px 24px rgba(35,30,22,.09)}.attendant-action--eggs{background:linear-gradient(110deg,#fff5f5,#fffafa)}.attendant-action--deaths{background:linear-gradient(110deg,#fff8f0,#fffdf9)}.attendant-action--feed{background:linear-gradient(110deg,#f3f9f1,#fbfdf9)}
    .attendant-action__icon{display:grid;place-items:center;width:88px;height:88px;border-radius:50%;background:#fce8e8;color:var(--worker-red)}.attendant-action--deaths .attendant-action__icon{background:#fff0e7}.attendant-action--feed .attendant-action__icon{background:#eaf4e9;color:#34864f}.attendant-action__icon svg{width:48px;height:48px}.attendant-action__copy strong{display:block;font-size:1.25rem;line-height:1.2;letter-spacing:-.025em}.attendant-action__copy small{display:block;margin-top:5px;color:var(--worker-muted);font-size:.95rem;line-height:1.45}.attendant-action__go{display:grid;place-items:center;width:46px;height:46px;border-radius:50%;background:var(--worker-red);color:#fff;font-size:1.65rem;line-height:1}
    .attendant-action[aria-disabled="true"]{opacity:.53;cursor:not-allowed;pointer-events:none}
    @container (max-width:900px){.attendant-actions{grid-template-columns:1fr;gap:10px}.attendant-action{min-height:108px;grid-template-columns:68px minmax(0,1fr) 38px;gap:12px;padding:13px 16px}.attendant-action__icon{width:64px;height:64px}.attendant-action__icon svg{width:38px;height:38px}.attendant-action__copy strong{font-size:1.08rem}.attendant-action__copy small{font-size:.86rem}.attendant-action__go{width:38px;height:38px}}
    .attendant-entries{padding:16px 19px 20px}.attendant-section-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px}.attendant-section-head h3{margin:0;font-size:1.35rem;letter-spacing:-.025em}.attendant-entry-list{display:grid;gap:7px}.attendant-entry{display:grid;grid-template-columns:48px minmax(0,1fr) auto;align-items:center;gap:13px;padding:14px 16px;border-radius:12px;background:#f8f6f2}.attendant-entry__icon{color:#8a7665}.attendant-entry__icon svg{width:30px;height:30px}.attendant-entry strong{display:block;font-size:.97rem}.attendant-entry small{display:block;color:var(--worker-muted);margin-top:3px;font-size:.85rem}.attendant-entry__qty{font-weight:800;font-size:.93rem;white-space:nowrap}.attendant-empty{display:flex;align-items:center;gap:16px;min-height:88px;padding:18px 23px;border-radius:12px;background:#f8f6f2}.attendant-empty svg{width:34px;height:34px;flex:0 0 34px;color:#8a7665}.attendant-empty strong{display:block;font-size:1rem}.attendant-empty p{margin:4px 0 0;color:var(--worker-muted);font-size:.9rem}
    .attendant-tasks{padding:16px 19px 19px}.attendant-tasks h3{margin:0 0 10px;font-size:1.1rem}.attendant-task{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:12px 14px;border-top:1px solid var(--worker-line);color:inherit;text-decoration:none}.attendant-task:first-of-type{border-top:0}.attendant-task span{font-weight:700}.attendant-task small{color:var(--worker-muted)}
    @media(max-width:900px){.attendant-actions{gap:10px}.attendant-action{grid-template-columns:68px minmax(0,1fr) 38px;gap:11px;padding:15px}.attendant-action__icon{width:64px;height:64px}.attendant-action__icon svg{width:38px;height:38px}.attendant-action__copy strong{font-size:1.05rem}.attendant-action__copy small{font-size:.83rem}.attendant-action__go{width:38px;height:38px}}
    @media(max-width:620px){.content:has(.attendant-home){padding:14px 12px 28px}.attendant-home{gap:12px}.attendant-hero{min-height:174px;padding:20px 18px;align-items:flex-start}.attendant-hero__copy{max-width:76%}.attendant-hero__art{width:58%;height:72%;opacity:.8}.attendant-hero__eyebrow{font-size:.68rem}.attendant-hero h2{font-size:1.55rem}.attendant-hero p:last-child{font-size:.83rem}.attendant-house{padding:15px}.attendant-house__select{min-height:62px;padding:10px 13px}.attendant-actions{grid-template-columns:1fr;gap:9px}.attendant-action{min-height:112px;grid-template-columns:66px minmax(0,1fr) 38px;padding:13px}.attendant-entries{padding:14px 12px}.attendant-section-head h3{font-size:1.2rem}.attendant-entry{grid-template-columns:34px minmax(0,1fr);gap:9px;padding:12px}.attendant-entry__icon svg{width:25px;height:25px}.attendant-entry__qty{grid-column:2;font-size:.84rem}.attendant-empty{padding:15px;gap:12px}}
    /* Compact worker dashboard proportions from the supplied reference. */
    .shell--plain:has(.attendant-home){max-width:760px;width:100%}
    .content:has(.attendant-home){padding:20px 18px 40px;background:#faf8f4}
    .attendant-home{gap:18px}
    .attendant-hero{min-height:136px;padding:20px 26px;border-radius:17px}
    .attendant-hero__copy{max-width:78%}
    .attendant-hero__eyebrow{font-size:.72rem;margin-bottom:6px}
    .attendant-hero h2{font-size:1.55rem}
    .attendant-hero p:last-child{font-size:.86rem;margin-top:7px}
    .attendant-hero__art{width:43%;opacity:.48}
    .attendant-house{padding:14px 16px}
    .attendant-house h3{font-size:1rem;margin-bottom:8px}
    .attendant-house form{max-width:100%}
    .attendant-house__select{min-height:56px;padding:9px 14px;gap:12px}
    .attendant-house__select svg{width:27px;height:27px;flex-basis:27px}
    .attendant-house__select select{font-size:.9rem}
    .attendant-actions{grid-template-columns:repeat(3,minmax(0,200px));justify-content:start;gap:12px}
    .attendant-action,.attendant-home .attendant-action{min-height:166px;display:flex;flex-direction:column;justify-content:center;gap:9px;padding:15px 12px;border:0;border-radius:14px;background:#a8272d;color:#fff;text-align:center;box-shadow:0 5px 12px rgba(70,20,20,.12)}
    .attendant-action__icon,.attendant-action--deaths .attendant-action__icon,.attendant-action--feed .attendant-action__icon{width:40px;height:34px;background:transparent;color:#fff}
    .attendant-action__icon svg{width:28px;height:28px}
    .attendant-action__copy strong{font-size:1rem;line-height:1.25}
    .attendant-action__copy small{margin-top:7px;color:#fff;font-size:.78rem;line-height:1.3}
    .attendant-action__go{display:none}
    .attendant-action:hover{background:#92232a;transform:translateY(-1px)}
    @media(max-width:620px){.shell--plain:has(.attendant-home){max-width:100%}.attendant-hero{min-height:132px;padding:18px 16px}.attendant-hero__copy{max-width:82%}.attendant-hero h2{font-size:1.35rem}.attendant-hero__art{width:38%;height:75%}.attendant-actions{grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.attendant-action,.attendant-home .attendant-action{min-height:140px;padding:12px 6px}.attendant-action__copy strong{font-size:.88rem}.attendant-action__copy small{font-size:.7rem}.attendant-entries{padding:14px 12px}}
    /* Main production dashboard reference layout */
    .shell--plain:has(.attendant-home){max-width:none;width:100%}
    .content:has(.attendant-home){max-width:none;padding:28px clamp(18px,4vw,60px) 56px;background:#faf8f4}
    .attendant-home{gap:18px}
    .attendant-hero{min-height:208px;padding:28px 4%;border-radius:18px}
    .attendant-hero__copy{max-width:620px}
    .attendant-hero__eyebrow{font-size:.83rem;margin-bottom:8px}
    .attendant-hero h2{font-size:clamp(1.8rem,3vw,2.45rem)}
    .attendant-hero p:last-child{font-size:1rem;margin-top:8px}
    .attendant-hero__art{width:min(55%,690px);height:100%;opacity:.97}
    .attendant-house{padding:17px 22px 20px}
    .attendant-house h3{font-size:1.16rem;margin-bottom:12px}
    .attendant-house form{max-width:min(100%,920px)}
    .attendant-house__select{min-height:72px;padding:12px 20px;gap:18px}
    .attendant-house__select svg{width:34px;height:34px;flex-basis:34px}
    .attendant-house__select select{font-size:1.08rem}
    .attendant-actions{grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
    .attendant-home .attendant-action{min-height:190px;display:grid;grid-template-columns:112px minmax(0,1fr) 46px;align-items:center;gap:16px;padding:20px 26px;border:1px solid var(--worker-line);border-radius:17px;color:var(--worker-ink);text-align:left;box-shadow:0 3px 12px rgba(35,30,22,.025)}
    .attendant-action--eggs{background:linear-gradient(110deg,#fff5f5,#fffafa)}
    .attendant-action--deaths{background:linear-gradient(110deg,#fff8f0,#fffdf9)}
    .attendant-action--feed{background:linear-gradient(110deg,#f3f9f1,#fbfdf9)}
    .attendant-home .attendant-action__icon,.attendant-home .attendant-action--deaths .attendant-action__icon,.attendant-home .attendant-action--feed .attendant-action__icon{display:grid;place-items:center;width:112px;height:112px;border-radius:50%;background:#fce8e8;color:var(--worker-red)}
    .attendant-home .attendant-action--deaths .attendant-action__icon{background:#fff0e7}
    .attendant-home .attendant-action--feed .attendant-action__icon{background:#eaf4e9;color:#34864f}
    .attendant-home .attendant-action__icon svg{width:58px;height:58px}
    .attendant-home .attendant-action__copy strong{font-size:1.25rem}
    .attendant-home .attendant-action__copy small{margin-top:5px;color:var(--worker-muted);font-size:.95rem;line-height:1.45}
    .attendant-home .attendant-action__go{display:grid;width:46px;height:46px;border-radius:50%;background:var(--worker-red);color:#fff;font-size:1.65rem}
    .attendant-entries{padding:16px 19px 20px}
    .shell--plain:has(.attendant-home) .topbar{display:flex;align-items:center;gap:16px;padding:8px clamp(16px,3vw,48px);background:#fff;color:#171b22;border-bottom:1px solid #eee9e1}
    .shell--plain:has(.attendant-home) .topbar-home{width:auto;justify-content:flex-start;color:#171b22}
    .shell--plain:has(.attendant-home) .topbar-home::after{content:'Brooks AgroFarm';margin-left:12px;color:#171b22;font-size:1.05rem;font-weight:800;white-space:nowrap}
    .attendant-topbar-user{display:flex;align-items:center;gap:11px;margin-left:auto;color:#171b22;text-decoration:none}
    .attendant-topbar-user span{display:grid;place-items:center;width:48px;height:48px;border-radius:50%;background:#f8e8e4;color:#a8272d;font-size:1.2rem;font-weight:700}
    .attendant-topbar-user strong{font-size:1rem}
    .attendant-topbar-user i{width:9px;height:9px;border-right:2px solid currentColor;border-bottom:2px solid currentColor;transform:rotate(45deg);margin:-5px 4px 0 3px}
    /* sign-out stays visible on the home screen too */
    /* Keep the compact worker footprint while using the reference dashboard styling. */
    .shell--plain:has(.attendant-home){max-width:760px;width:100%}
    .shell--plain:has(.attendant-home) .content{padding:20px 18px 40px}
    .shell--plain:has(.attendant-home) .topbar{padding:8px 16px}
    .shell--plain:has(.attendant-home) .topbar-mark{width:42px;height:42px}
    .shell--plain:has(.attendant-home) .topbar-home::after{font-size:.95rem;margin-left:9px}
    .attendant-topbar-user span{width:38px;height:38px;font-size:1rem}
    .attendant-topbar-user strong{font-size:.88rem}
    .attendant-hero{min-height:136px;padding:18px 22px}
    .attendant-hero__copy{max-width:75%}
    .attendant-hero__eyebrow{font-size:.72rem;margin-bottom:6px}
    .attendant-hero h2{font-size:1.55rem}
    .attendant-hero p:last-child{font-size:.86rem;margin-top:7px}
    .attendant-hero__art{width:45%;opacity:.72}
    .attendant-house{padding:14px 16px}
    .attendant-house h3{font-size:1rem;margin-bottom:8px}
    .attendant-house__select{min-height:56px;padding:9px 14px;gap:12px}
    .attendant-house__select svg{width:27px;height:27px;flex-basis:27px}
    .attendant-house__select select{font-size:.9rem}
    .attendant-actions{gap:10px}
    .attendant-home .attendant-action{min-height:140px;grid-template-columns:68px minmax(0,1fr) 36px;gap:10px;padding:13px 14px;border-radius:15px}
    .attendant-home .attendant-action__icon,.attendant-home .attendant-action--deaths .attendant-action__icon,.attendant-home .attendant-action--feed .attendant-action__icon{width:64px;height:64px}
    .attendant-home .attendant-action__icon svg{width:38px;height:38px}
    .attendant-home .attendant-action__copy strong{font-size:1.03rem}
    .attendant-home .attendant-action__copy small{font-size:.82rem;line-height:1.35}
    .attendant-home .attendant-action__go{width:36px;height:36px;font-size:1.35rem}
    .attendant-entries{padding:14px 16px 16px}
    .attendant-section-head h3{font-size:1.15rem}
    .shell--plain:has(.attendant-home) .attendant-home .attendant-action{color:var(--worker-ink);border:1px solid var(--worker-line);box-shadow:0 3px 12px rgba(35,30,22,.035)}
    .shell--plain:has(.attendant-home) .attendant-home .attendant-action--eggs{background:linear-gradient(110deg,#fff5f5,#fffafa)}
    .shell--plain:has(.attendant-home) .attendant-home .attendant-action--deaths{background:linear-gradient(110deg,#fff8f0,#fffdf9)}
    .shell--plain:has(.attendant-home) .attendant-home .attendant-action--feed{background:linear-gradient(110deg,#f3f9f1,#fbfdf9)}
    .shell--plain:has(.attendant-home) .attendant-home .attendant-action__icon{background:#fce8e8;color:var(--worker-red)}
    .shell--plain:has(.attendant-home) .attendant-home .attendant-action--deaths .attendant-action__icon{background:#fff0e7;color:var(--worker-red)}
    .shell--plain:has(.attendant-home) .attendant-home .attendant-action--feed .attendant-action__icon{background:#eaf4e9;color:#34864f}
    .shell--plain:has(.attendant-home) .attendant-home .attendant-action__copy small{color:var(--worker-muted)}
    .shell--plain:has(.attendant-home) .attendant-home .attendant-action__go{display:grid;background:var(--worker-red);color:#fff}
    @media(max-width:620px){.shell--plain:has(.attendant-home){max-width:100%}.shell--plain:has(.attendant-home) .content{padding:14px 12px 28px}.attendant-home{gap:12px}.attendant-hero{min-height:132px;padding:18px 16px;align-items:flex-start}.attendant-hero__copy{max-width:82%}.attendant-hero h2{font-size:1.35rem}.attendant-hero__art{width:38%;height:75%;opacity:.65}.attendant-actions{grid-template-columns:1fr;gap:9px}.attendant-home .attendant-action{min-height:108px;grid-template-columns:66px minmax(0,1fr) 38px;padding:12px}.attendant-home .attendant-action__icon{width:60px;height:60px}.attendant-home .attendant-action__icon svg{width:36px;height:36px}.attendant-entries{padding:14px 12px}.attendant-entry{grid-template-columns:34px minmax(0,1fr);gap:9px;padding:12px}.attendant-entry__qty{grid-column:2;font-size:.84rem}.attendant-empty{padding:15px;gap:12px}}
    @media(max-width:900px){.attendant-home .attendant-action{min-height:150px;grid-template-columns:76px minmax(0,1fr) 38px;gap:12px;padding:15px}.attendant-home .attendant-action__icon{width:70px;height:70px}.attendant-home .attendant-action__icon svg{width:40px;height:40px}.attendant-home .attendant-action__copy strong{font-size:1.05rem}.attendant-home .attendant-action__copy small{font-size:.83rem}.attendant-home .attendant-action__go{width:38px;height:38px}}
    @media(max-width:620px){.content:has(.attendant-home){padding:14px 12px 28px}.attendant-home{gap:12px}.attendant-hero{min-height:174px;padding:20px 18px;align-items:flex-start}.attendant-hero__copy{max-width:76%}.attendant-hero__art{width:58%;height:72%;opacity:.8}.attendant-hero__eyebrow{font-size:.68rem}.attendant-hero h2{font-size:1.55rem}.attendant-hero p:last-child{font-size:.83rem}.attendant-house{padding:15px}.attendant-house__select{min-height:62px;padding:10px 13px}.attendant-actions{grid-template-columns:1fr;gap:9px}.attendant-home .attendant-action{min-height:112px;grid-template-columns:66px minmax(0,1fr) 38px;padding:13px}.attendant-entries{padding:14px 12px}.attendant-section-head h3{font-size:1.2rem}.attendant-entry{grid-template-columns:34px minmax(0,1fr);gap:9px;padding:12px}.attendant-entry__icon svg{width:25px;height:25px}.attendant-entry__qty{grid-column:2;font-size:.84rem}.attendant-empty{padding:15px;gap:12px}}
</style>
@endpush

@php
    $hour = now()->hour;
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    $workerName = $user->firstName();
    $actionRoutes = ['eggs' => 'daily.eggs', 'deaths' => 'daily.deaths', 'feed' => 'daily.feed', 'water' => 'daily.water', 'sick' => 'daily.sick', 'problem' => 'daily.problem'];
    $mayReport = \App\Http\Controllers\Poultry\HouseReportController::mayReport($user);
    $hasActions = $selectedHouse && (
        $user->canDo('egg-entry', 'create') || $user->canDo('mortality-entry', 'create') || $user->canDo('feed-consumption', 'create')
    );
@endphp

<div class="attendant-home">
    <section class="attendant-hero">
        <div class="attendant-hero__copy"><p class="attendant-hero__eyebrow">Daily operations</p><h2>{{ $greeting }}, {{ $workerName }}</h2><p>{{ now()->format('l, j F Y') }}<br>Choose what to record for your flock today.</p></div>
        <svg class="attendant-hero__art" viewBox="0 0 760 230" preserveAspectRatio="xMaxYMax meet" aria-hidden="true">
            <defs><linearGradient id="worker-sky" x1="0" x2="1"><stop stop-color="#fffaf4" stop-opacity="0"/><stop offset="1" stop-color="#f9dfc1" stop-opacity=".86"/></linearGradient><linearGradient id="worker-hill" x1="0" y1="0" x2="0" y2="1"><stop stop-color="#f7d7b4"/><stop offset="1" stop-color="#f3bf91"/></linearGradient></defs>
            <path fill="url(#worker-sky)" d="M145 0h615v230H0Q230 162 310 172T490 117 760 69V0Z"/><circle cx="554" cy="42" r="35" fill="#f6d5ae" opacity=".52"/>
            <path d="M244 177 355 112l105 65M283 159v-35l72-43 70 42v54M333 178v-33h38v33M409 151h28v27" fill="#fff4e6" stroke="#eac49f" stroke-width="4" opacity=".8"/>
            <path d="M123 202q155-40 273-10t364-21v59H0Z" fill="url(#worker-hill)" opacity=".55"/><path d="M178 186h250m24 0h155" stroke="#e4bd97" stroke-width="4" stroke-dasharray="8 8" opacity=".8"/>
            <g transform="translate(602 150) scale(1.14)"><ellipse cx="0" cy="18" rx="43" ry="28" fill="#bd4c19"/><circle cx="30" cy="-7" r="22" fill="#c44518"/><path d="M17-27q7-19 15 0 11-16 14 2" fill="#b72d1f"/><path d="m48-8 25 8-24 8" fill="#eda926"/><circle cx="36" cy="-11" r="3" fill="#1e211d"/><path d="M-30 11q-39-38-31 5 17 24 37 17" fill="#d85d20"/><path d="M-10 43v16m20-16v16" stroke="#d38224" stroke-width="6"/><path d="M-17 59h14m10 0h14" stroke="#9a5d24" stroke-width="5" stroke-linecap="round"/></g>
            <g transform="translate(514 169) scale(.78)"><ellipse cx="0" cy="18" rx="38" ry="25" fill="#e99d38"/><circle cx="27" cy="-5" r="18" fill="#f0ad48"/><path d="M18-22q7-15 13 0" fill="#cf4430"/><path d="m42-6 19 7-19 6" fill="#d28c1e"/><circle cx="31" cy="-8" r="2.5" fill="#20211f"/><path d="M-29 11q-31-30-27 3 14 20 31 16" fill="#f0b34b"/><path d="M-8 39v14m16-14v14" stroke="#bd7b24" stroke-width="5"/></g>
            <g transform="translate(461 181) scale(.52)"><ellipse cx="0" cy="18" rx="34" ry="23" fill="#df8d31"/><circle cx="24" cy="-4" r="17" fill="#eea544"/><path d="M17-20q6-14 12 0" fill="#c7432e"/><path d="m40-5 17 6-17 6" fill="#d28c1e"/><circle cx="28" cy="-7" r="2.4" fill="#20211f"/><path d="M-24 11q-29-29-25 2 13 19 28 16" fill="#d88a32"/><path d="M-7 38v13m14-13v13" stroke="#bd7b24" stroke-width="5"/></g>
        </svg>
    </section>

    @if(session('done'))
        <div class="attendant-panel" style="padding:13px 18px;color:#277345"><strong>Saved:</strong> {{ session('done.what') }} — {{ session('done.where') }}</div>
    @endif

    @if($houseChoices->isEmpty())
        <section class="attendant-panel attendant-house">
            <div class="attendant-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m3 10 9-7 9 7M5 9v12h14V9M9 21v-7h6v7"/></svg><div><strong>No house assigned yet</strong><p>Ask your supervisor to assign you to a poultry house before recording today's work.</p></div></div>
        </section>
    @endif

    <section class="attendant-actions" aria-label="Record today's poultry work">
        @foreach([
            ['permission'=>'egg-entry','slug'=>'eggs','label'=>'Eggs / Amagi','detail'=>'Record eggs collected today.','icon'=>'egg','class'=>'eggs'],
            ['permission'=>'mortality-entry','slug'=>'deaths','label'=>'Deaths / Ezifudde','detail'=>'Record mortalities and culls today.','icon'=>'dead-hen','class'=>'deaths'],
            ['permission'=>'feed-consumption','slug'=>'feed','label'=>'Feeds / Emmere','detail'=>'Record feed given to the flock today.','icon'=>'sack','class'=>'feed'],
            {{-- Added after the Farm Hub survey: checked by the supervisor like the three above. --}}
            ['permission'=>null,'slug'=>'water','label'=>'Water / Amazzi','detail'=>'Record water given to the house today.','icon'=>'drop','class'=>'water'],
            ['permission'=>null,'slug'=>'sick','label'=>'Sick birds / Enkoko endwadde','detail'=>'Report birds that look sick or weak.','icon'=>'thermo','class'=>'sick'],
            ['permission'=>null,'slug'=>'problem','label'=>'Problem / Ekizibu','detail'=>'Report something broken or wrong in the house.','icon'=>'alert','class'=>'problem'],
        ] as $action)
            @continue($action['permission'] ? !$user->canDo($action['permission'], 'create') : !$mayReport)
            @if($selectedHouse)
                <a class="attendant-action attendant-action--{{ $action['class'] }}" href="{{ route($actionRoutes[$action['slug']], ['batch'=>$selectedHouse->animal_group_id, 'house'=>$selectedHouse->id]) }}">
            @else
                <a class="attendant-action attendant-action--{{ $action['class'] }}" aria-disabled="true" href="#house-required">
            @endif
                    {{-- Farm Hub card: icon tile, English name, Luganda name, what it is for, then a full-width Record button. --}}
                    @php([$english, $luganda] = array_pad(array_map('trim', explode('/', $action['label'], 2)), 2, null))
                    <span class="attendant-action__icon"><svg aria-hidden="true"><use href="#i-{{ $action['icon'] }}"></use></svg></span>
                    <span class="attendant-action__copy"><strong>{{ $english }}</strong>@if($luganda)<em>{{ $luganda }}</em>@endif<small>{{ $action['detail'] }}</small></span>
                    <span class="attendant-action__go">{{ in_array($action['slug'], ['sick', 'problem'], true) ? 'Report' : 'Record' }} {{ $action['slug'] === 'problem' ? 'a problem' : strtolower($english) }}</span>
                </a>
        @endforeach
    </section>

    <section class="attendant-panel attendant-entries" id="today-entries">
        <div class="attendant-section-head"><h3>Today's entries</h3></div>
        @forelse($todayEntries as $entry)
            <div class="attendant-entry"><span class="attendant-entry__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 3h9l4 4v14H6zM15 3v5h4M9 12h7M9 16h7"/></svg></span><span><strong>{{ $entry['label'] }}</strong><small>{{ \Illuminate\Support\Carbon::parse($entry['time'])->format('g:i A') }} · {{ $entry['house'] }}</small></span><span class="attendant-entry__qty">{{ $entry['quantity'] }}</span></div>
        @empty
            <div class="attendant-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 3h9l4 4v14H6zM15 3v5h4M9 12h7M9 16h7"/></svg><div><strong>No entries recorded yet today.</strong><p>Tap one of the options above to record your first entry.</p></div></div>
        @endforelse
    </section>

    @if($myTasks->isNotEmpty())
        <section class="attendant-panel attendant-tasks"><h3>My assigned tasks</h3>
            @foreach($myTasks as $task)
                <a class="attendant-task" href="{{ route('my-tasks.record', $task->id) }}"><span>{{ $task->task_name }}</span><small>{{ $myTaskToday->contains($task->id) ? 'Recorded today · add another' : 'Tap to record' }}</small></a>
            @endforeach
        </section>
    @endif
</div>
@endsection
