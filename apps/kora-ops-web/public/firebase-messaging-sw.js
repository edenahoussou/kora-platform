importScripts('https://www.gstatic.com/firebasejs/11.10.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/11.10.0/firebase-messaging-compat.js');

const configParam = new URL(self.location.href).searchParams.get('firebaseConfig');
if (configParam) {
    firebase.initializeApp(JSON.parse(configParam));
    firebase.messaging();
}
