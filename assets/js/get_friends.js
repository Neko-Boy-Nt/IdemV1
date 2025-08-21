// friends.js
function loadFriends() {
    fetch('../../api/friends.php')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const list = document.getElementById('friends-to-message');
                list.innerHTML = '';
                data.friends.forEach(friend => {
                    const div = document.createElement('div');
                    div.className = 'friend-item';
                    div.innerHTML = `
                        <img src="upload/avatars/${friend.avatar || 'default.jpg'}" alt="Avatar">
                        <span>${friend.first_name} ${friend.last_name}</span>
                    `;
                    div.addEventListener('click', () => messagingSystem.startConversation(friend.id));
                    list.appendChild(div);
                });
            }
        })
        .catch(err => console.error('Erreur chargement amis', err));
}