let currentUnbanIp = null;

function vgt_trigger_unban_modal(ip) {
    currentUnbanIp = ip;
    document.getElementById('vgt-modal-ip-display').innerText = ip;
    
    const modal = document.getElementById('vgt-unban-modal');
    modal.style.display = 'flex';
    // Kleine Verzögerung für CSS Transition
    setTimeout(() => { modal.classList.add('active'); }, 10);
}

function vgt_close_unban_modal() {
    const modal = document.getElementById('vgt-unban-modal');
    modal.classList.remove('active');
    setTimeout(() => { 
        modal.style.display = 'none'; 
        currentUnbanIp = null;
        // Reset Button falls er auf "Processing" stand
        const btn = document.getElementById('vgt-execute-unban-btn');
        btn.innerHTML = 'EXECUTE UNBAN';
        btn.classList.remove('processing');
    }, 300);
}

document.getElementById('vgt-execute-unban-btn').addEventListener('click', function(e) {
    e.preventDefault();
    if (!currentUnbanIp) return;
    
    const btn = this;
    btn.innerHTML = '<span class="pulse-dot"></span> PROCESSING...';
    btn.classList.add('processing');
    
    jQuery.post(ajaxurl, {
        action: 'vis_dashboard_unban_ip',
        ip: currentUnbanIp,
        nonce: '<?php echo wp_create_nonce("vis_dashboard_nonce"); ?>' 
    }, function(response) {
        if (response.success) {
            btn.innerHTML = 'SUCCESS';
            btn.style.background = '#10b981'; // Grün bei Erfolg
            btn.style.borderColor = '#10b981';
            setTimeout(() => { location.reload(); }, 600);
        } else {
            alert('VGT DB ERROR: ' + (response.data || 'Unban failed.'));
            vgt_close_unban_modal();
        }
    }).fail(function() {
        alert('VGT NETWORK ERROR: Server Uplink failed.');
        vgt_close_unban_modal();
    });
});
