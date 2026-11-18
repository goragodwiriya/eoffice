/**
 * modules/telegramgroup/admin.js — กลุ่ม Telegram ที่รับข้อความแจ้งเตือน
 */
EventManager.on('router:initialized', () => {
  RouterManager.register('/telegram-groups', {
    template: 'telegramgroup/groups.html',
    title: '{LNG_Telegram groups}',
    requireAuth: true
  });
});
