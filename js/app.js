document.addEventListener('DOMContentLoaded', function () {
  const method = document.getElementById('payment-method');
  const note = document.getElementById('payment-note');
  if (!method || !note) return;
  const updateNote = () => {
    const selected = method.value;
    note.textContent = selected === 'paypal'
      ? '您已选择 PayPal 付款，我们将在提交后为您生成安全支付链接。'
      : '您已选择 Stripe 付款，提交后请继续完成信用卡支付。';
  };
  method.addEventListener('change', updateNote);
  updateNote();
});
