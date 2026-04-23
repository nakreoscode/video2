<?php // includes/footer.php ?>
</div><!-- /main -->
<?php if(get_setting('age_warning_enabled','0')==='1' && !isset($_COOKIE['age_confirmed'])): ?>
<div id="age-modal" style="position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.85);display:flex;align-items:center;justify-content:center;padding:20px">
  <div style="background:var(--bg2);border:1px solid var(--border);border-radius:16px;padding:36px;max-width:440px;width:100%;text-align:center">
    <div style="font-size:64px;margin-bottom:16px">🔞</div>
    <h2 style="font-size:22px;font-weight:700;margin-bottom:10px">Yaş Doğrulama</h2>
    <p style="font-size:15px;color:var(--text2);margin-bottom:24px;line-height:1.6">
      Bu site 18 yaş ve üzeri kullanıcılara yönelik içerikler barındırmaktadır.<br>
      Devam etmek için yaşınızı doğrulayın.
    </p>
    <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
      <button onclick="confirmAge()" class="nsbtn" style="border-radius:20px;padding:12px 28px;font-size:15px">✅ 18 Yaşındayım</button>
      <button onclick="denyAge()" class="nsbtn ghost" style="border-radius:20px;padding:12px 28px;font-size:15px">❌ Değilim</button>
    </div>
    <p style="font-size:11px;color:var(--text3);margin-top:16px">Devam ederek kullanım koşullarını kabul etmiş sayılırsınız.</p>
  </div>
</div>
<script>
function confirmAge(){document.cookie='age_confirmed=1;path=/;max-age=86400';document.getElementById('age-modal').remove();}
function denyAge(){location.href='https://www.google.com';}
document.getElementById('age-modal').addEventListener('click',function(e){if(e.target===this){}});
</script>
<?php endif?>
</body>
</html>
