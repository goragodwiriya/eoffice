/**
 * modules/repair/admin.js
 *
 * ลงทะเบียน route และ helper ของโมดูลแจ้งซ่อม
 */
EventManager.on('router:initialized', () => {
  RouterManager.register('/repair-receive', {
    template: 'repair/receive.html',
    title: '{LNG_Get a repair}',
    requireAuth: true
  });

  RouterManager.register('/repair-history', {
    template: 'repair/history.html',
    title: '{LNG_Repair history}',
    requireAuth: true
  });

  RouterManager.register('/repair-jobs', {
    template: 'repair/jobs.html',
    title: '{LNG_Repair list}',
    requireAuth: true
  });

  RouterManager.register('/repair-detail', {
    template: 'repair/detail.html',
    title: '{LNG_Repair job description}',
    menuPath: '/repair-jobs',
    requireAuth: true
  });

  RouterManager.register('/repair-statuses', {
    template: 'repair/statuses.html',
    title: '{LNG_Repair status}',
    requireAuth: true
  });

  RouterManager.register('/repair-settings', {
    template: 'repair/settings.html',
    title: '{LNG_Module Settings} {LNG_Repair}',
    requireAuth: true
  });

});