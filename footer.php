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
