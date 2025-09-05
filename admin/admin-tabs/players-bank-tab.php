<!-- <div class="wrap">
    <h2>📥 Import Player Bank Accounts</h2>

    <form id="import-form" style="margin-top: 20px;">
        <button type="submit" class="button button-primary">📥 Run Import from Plugin JSON</button>
    </form>
</div>

<script>
document.getElementById('import-form').addEventListener('submit', function (e) {
    e.preventDefault();

    const formData = new FormData();
    formData.append('action', 'hs_dayz_players_import');

    fetch(ajaxurl, {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(d => alert(d.success ? '✅ Import completed!' : '❌ Import failed: ' + d.data.message));
});
</script> -->
