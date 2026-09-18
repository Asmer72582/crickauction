const io = require('socket.io-client');

const URL = process.env.URL || 'http://localhost:3000';
const ROOM = process.env.ROOM || 'match1';

console.log(`Connecting to ${URL} room=${ROOM}`);
const socket = io(URL, { reconnectionAttempts: 3 });

socket.on('connect', () => {
  console.log('Connected');
  socket.emit('join-room', ROOM);
  setTimeout(() => {
    // Send a sample state patch
    socket.emit('update-state', { room: ROOM, patch: { matchTitle: 'Integration Test Match', matchNo: '99' } });
    // Trigger animations
    setTimeout(() => socket.emit('trigger-animation', { room: ROOM, animation: 'four' }), 500);
    setTimeout(() => socket.emit('trigger-animation', { room: ROOM, animation: 'six' }), 1200);
    setTimeout(() => socket.emit('trigger-animation', { room: ROOM, animation: 'wicket' }), 2000);
    // Send a scoring event
    setTimeout(() => socket.emit('score-ball', { room: ROOM, runs: 4 }), 2600);
    // End after actions
    setTimeout(() => {
      console.log('Done sequence; disconnecting.');
      socket.disconnect();
      process.exit(0);
    }, 4200);
  }, 200);
});

socket.on('state-update', (s) => {
  console.log('state-update received:', { matchTitle: s.matchTitle, teamA: s.teamA.score, teamB: s.teamB.score });
});

socket.on('play-animation', (a) => {
  console.log('play-animation', a.animation, a.payload || '');
});

socket.on('connect_error', (err) => {
  console.error('connect_error', err.message);
});
