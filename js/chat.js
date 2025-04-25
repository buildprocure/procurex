document.addEventListener('DOMContentLoaded', () => {
    console.log('Chat script loaded');
  
    const form = document.querySelector('#chat-form');
    const input = document.querySelector('#user-input');
    const chatBox = document.querySelector('#chat-box');
    const toggleBtn = document.querySelector('#chat-toggle');
    const chatContainer = document.querySelector('#chat-container');
  
    // Toggle chat box
    toggleBtn.addEventListener('click', () => {
        console.log('Toggle button clicked');
      chatContainer.style.display = chatContainer.style.display === 'none' ? 'flex' : 'none';
      toggleBtn.style.display = 'none';
    });
  
    // Handle form submission
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const userMessage = input.value.trim();
      if (!userMessage) return;
  
      chatBox.innerHTML += `<div><strong>You:</strong> ${userMessage}</div>`;
      input.value = '';
      
      console.log('User message:', userMessage);
      const response = await fetch('../chatbot.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message: userMessage })
      });
  
      const data = await response.json();
      console.log('Response from server after json:', data);
      chatBox.innerHTML += `<div><strong>Bot:</strong> ${data.reply}</div>`;
      chatBox.scrollTop = chatBox.scrollHeight;
    });
  });
  