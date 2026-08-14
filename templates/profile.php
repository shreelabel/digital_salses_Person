<?php declare(strict_types=1); /** @var array $activeUser */ ?>
<div class="page" data-page="profile">
  <div class="grid" style="grid-template-columns:1fr 1fr;gap:18px">
    <div class="card">
      <div class="card-h"><h3>Account</h3></div>
      <div class="detail-row"><span class="k">Name</span><span class="v"><?= e($activeUser['name']) ?></span></div>
      <div class="detail-row"><span class="k">Email</span><span class="v"><?= e($activeUser['email']) ?></span></div>
      <div class="detail-row"><span class="k">Role</span><span class="v"><strong style="color:var(--primary);"><?= e(ucfirst($activeUser['role'])) ?></strong></span></div>
      <div class="detail-row"><span class="k">AI Lead Finder</span><span class="v"><?= \SLC\Core\Auth::can('ai_lead_finder.view', $activeUser) ? '<span class="badge" style="background:rgba(34,197,94,0.15); color:var(--good);">FULL ACCESS</span>' : '<span class="badge" style="background:rgba(239,68,68,0.15); color:var(--bad);">OFF</span>' ?></span></div>
      <div class="detail-row"><span class="k">Configuration</span><span class="v"><?= \SLC\Core\Auth::can('configuration.view', $activeUser) ? '<span class="badge" style="background:rgba(34,197,94,0.15); color:var(--good);">FULL ACCESS</span>' : '<span class="badge" style="background:rgba(239,68,68,0.15); color:var(--bad);">OFF</span>' ?></span></div>
      <div class="detail-row"><span class="k">Last login</span><span class="v"><?= e($activeUser['last_login_at'] ?? '—') ?></span></div>
    </div>
    <div class="card">
      <div class="card-h"><h3>Change Password</h3></div>
      <div class="field" style="margin-bottom:10px"><label class="fld">Current password</label><input class="fld" id="curPwd" type="password"></div>
      <div class="field" style="margin-bottom:10px"><label class="fld">New password</label><input class="fld" id="newPwd" type="password"></div>
      <div class="field" style="margin-bottom:14px"><label class="fld">Confirm new password</label><input class="fld" id="confPwd" type="password"></div>
      <button class="btn-primary" id="pwdSave">Update Password</button>
    </div>
  </div>
</div>
