<!-- footer.php -->
<style>
  #chat-toggle {
    position: fixed;
    bottom: 20px;
    right: 20px;
    background-color: yellow;
    color: black;
    padding: 10px 20px;
    border: none;
    border-radius: 25px;
    font-weight: bold;
    cursor: pointer;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
    z-index: 1000;
  }

  #chat-container {
    position: fixed;
    bottom: 20px;
    right: 20px;
    width: 300px;
    background: white;
    border: 1px solid #ccc;
    border-radius: 12px;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
    display: none;
    flex-direction: column;
    padding: 10px;
    z-index: 999;
    transition: all 0.3s ease;
  }

  #chat-form {
    display: flex;
    gap: 5px;
  }

  #user-input {
    flex: 1;
    padding: 5px;
  }

  #chat-box {
    max-height: 200px;
    overflow-y: auto;
    background: #f9f9f9;
    margin-bottom: 10px;
    padding: 5px;
    border-radius: 6px;
  }
</style>

<!-- Chat Toggle Button -->
<button id="chat-toggle">Chat us</button>

<!-- Chat UI -->
<div id="chat-container" style="display: none;">
  <div id="chat-box"></div>
  <form id="chat-form">
    <input type="text" id="user-input" placeholder="Type your message..." />
    <button type="submit">Send</button>
  </form>
</div>

<!-- Chat Script -->
<script src="/js/chat.js"></script>
<script>
  document.addEventListener('click', function(event) {
    // Don't interfere with selectpicker elements
    if (event.target.closest('.bootstrap-select') || event.target.closest('.selectpicker')) {
        return;
    }
    
    // Handle Switch Buyer Link
    if (event.target.classList.contains('js-switch-buyer')) {
        event.preventDefault();
        event.stopPropagation();
        
        const form = event.target.closest('form');
        const select = form.querySelector('select[name="buyer_id"]');
        
        if (select && select.value) {
            form.submit();
        } else {
            alert('Please select a buyer first.');
        }
    }

    // Handle Restore Admin Link
    if (event.target.classList.contains('js-restore-admin')) {
        event.preventDefault();
        event.stopPropagation();
        event.target.closest('form').submit();
    }
});

// Alternative handler for switch button using jQuery for better compatibility with selectpicker
$(document).on('click', '.js-switch-buyer', function(e) {
    e.preventDefault();
    e.stopPropagation();
    
    var form = $(this).closest('form');
    var buyerId = form.find('#buyerSelect').val();
    
    if (buyerId && buyerId !== '') {
        form.submit();
    } else {
        alert('Please select a buyer first.');
    }
});

// Initialize Bootstrap Selectpicker and provide manual toggle fallback
$(document).ready(function() {
  var $select = $('#buyerSelect');
  if ($select.length === 0) return;

  // initialize with container body to avoid clipping
  $select.selectpicker({
    container: 'body',
    liveSearch: true,
    title: 'Select a Buyer',
    width: '100%'
  });
  console.log('Selectpicker initialized successfully');

  // obtain the plugin menu if available
  var sp = $select.data('selectpicker');
  var $menu = (sp && sp.$menu) ? sp.$menu : $select.parent().find('.dropdown-menu');

  // Let the plugin and Bootstrap handle toggling. Ensure Bootstrap's Dropdown is initialized.
  var $button = $select.parent().find('.dropdown-toggle');
  if ($button.length) {
    try {
      if (typeof bootstrap !== 'undefined' && bootstrap.Dropdown) {
        // create instance so Bootstrap's handlers are ready
        bootstrap.Dropdown.getOrCreateInstance($button[0]);
      }
    } catch (e) {
      console.log('Bootstrap Dropdown init error (non-fatal):', e);
    }
  }

    // Fallback: build a simple custom searchable picker if bootstrap-select liveSearch is unreliable
    (function() {
      var $orig = $('#buyerSelect');
      if ($orig.length === 0) return;

      // create custom UI only once
      if ($orig.data('vb-initialized')) return;
      $orig.data('vb-initialized', true);

      var options = $orig.find('option').map(function() {
        return { val: $(this).val(), text: $(this).text().trim() };
      }).get().filter(function(o) { return o.val !== '' });

      console.log('VB Picker: found options count=', options.length, options.map(function(o){return o.text;}));

      var $wrap = $('<div class="vb-picker" style="position:relative; display:inline-block; width:100%;"></div>');
      var $display = $('<div class="vb-display btn btn-primary" style="width:100%; text-align:left;"></div>').text('Select a Buyer');
      var $menu = $(
        '<div class="vb-menu" style="position:absolute; display:none; z-index:100000; background:#fff; border:1px solid #ddd; width:100%; box-shadow:0 4px 12px rgba(0,0,0,0.08); padding:8px;">'
        + '<input class="vb-search form-control" placeholder="Search buyers" style="margin-bottom:8px;">'
        + '<ul class="vb-list" style="list-style:none;padding:0;margin:0;max-height:220px;overflow:auto;"></ul>'
        + '</div>'
      );

      var $list = $menu.find('.vb-list');
      options.forEach(function(o, idx) {
        var $li = $('<li></li>');
        $li.text(o.text).attr('data-val', o.val).attr('data-idx', idx);
        // explicit styling to ensure visibility despite global CSS
        $li.css({
          padding: '6px 8px',
          cursor: 'pointer',
          borderRadius: '4px',
          color: '#333',
          backgroundColor: 'transparent',
          display: 'block',
          fontSize: '14px'
        });
        $li.on('mouseenter', function() { $(this).css('background', '#f1f1f1'); }).on('mouseleave', function() { $(this).css('background','transparent'); });
        $li.on('click', function(e) {
          e.preventDefault();
          var v = $(this).attr('data-val');
          $orig.val(v).trigger('change');
          $display.text($(this).text());
          $menu.hide();
        });
        $list.append($li);
      });

      $display.on('click', function(e) {
        e.stopPropagation();
        $('.vb-menu').not($menu).hide();
        $menu.toggle();
        $menu.find('.vb-search').focus().select();
      });

      $menu.on('click', function(e) { e.stopPropagation(); });
      // ensure menu and search input are visible and styled
      $menu.css({ color: '#333' });
      $menu.find('.vb-search').css({ color: '#333' });

      $menu.find('.vb-search').on('input', function() {
        var q = $(this).val().toLowerCase();
        $list.children().each(function() {
          var txt = $(this).text().toLowerCase();
          $(this).toggle(txt.indexOf(q) !== -1);
        });
      });

      $(document).on('click', function() { $menu.hide(); });

      $wrap.append($display).append($menu);
      // place the custom picker outside the plugin container so hiding the plugin won't hide our UI
      var $bsContainer = $orig.parent('.bootstrap-select');
      if ($bsContainer.length) {
        $bsContainer.after($wrap);
        $bsContainer.hide();
        console.log('VB Picker: inserted after bootstrap-select container and hiding plugin container');
      } else {
        $orig.after($wrap);
      }
      $orig.hide();
      // ensure visible and styled
      $wrap.css({ display: 'block' });
      console.log('VB Picker: wrapper inserted, visible=', $wrap.is(':visible'), 'parent=', $wrap.parent().attr('class'));
    })();

  // clicking a menu item should set value and close menu
  $(document).on('click.selectpickerItem', '.bootstrap-select .dropdown-menu li a', function(e) {
    e.preventDefault();
    e.stopPropagation();
    var $a = $(this);
    var idx = $a.attr('data-original-index');
    if (typeof idx !== 'undefined') {
      var val = $select.find('option').eq(idx).val();
      $select.selectpicker('val', val);
      // close
      if ($menu && $menu.length) $menu.hide();
      $button.attr('aria-expanded', 'false');
      $select.parent().removeClass('open');
    }
  });

    // close on outside click
    $(document).on('click.selectpickerClose', function(e) {
    if (window.__selectpickerIgnoreClicks) return;
    if ($(e.target).closest('.bootstrap-select').length === 0) {
      if ($menu && $menu.length) $menu.hide();
      $button.attr('aria-expanded', 'false');
      $select.parent().removeClass('open');
    }
  });
});
</script>