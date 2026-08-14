/* profile.js */
(function () {
  'use strict';
  const SLC = window.SLC || {};
  const api = SLC.api;
  document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('pwdSave').addEventListener('click', async () => {
      const cur = document.getElementById('curPwd').value;
      const nw = document.getElementById('newPwd').value;
      const cf = document.getElementById('confPwd').value;
      if (!cur || !nw) { SLC.toast('Fill in current and new password', 'error'); return; }
      if (nw !== cf) { SLC.toast('New passwords do not match', 'error'); return; }
      if (nw.length < 8) { SLC.toast('New password must be at least 8 characters', 'error'); return; }
      try { await api.post('auth/change-password', { current_password: cur, new_password: nw }); SLC.toast('Password updated', 'success'); document.getElementById('curPwd').value = ''; document.getElementById('newPwd').value = ''; document.getElementById('confPwd').value = ''; } catch (e) { SLC.toast(e.message, 'error'); }
    });
  });
})();
