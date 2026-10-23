import { ref, computed } from 'vue';

// Shared singleton state for agent break timer
const isRunning = ref(false);
const isPaused = ref(false);
const mode = ref('countdown'); // 'countdown' | 'stopwatch'
const breakType = ref('15m'); // '15m' | '30m' | '45m' | '60m' | 'custom' | 'stopwatch'
const breakLabel = ref('Short Break');
const targetMinutes = ref(15);

// Timestamps and time values (in milliseconds)
const startTimestamp = ref(null);
const targetEndTime = ref(null);
const pausedRemainingMs = ref(0);
const elapsedMs = ref(0);
const remainingMs = ref(0);

// Sound alert preference
const soundEnabled = ref(localStorage.getItem('agent_break_sound') !== 'false');

// History of last completed break
const lastBreakRecord = ref(null);

let intervalId = null;
let hasChimed = false;

// Audio Chime using Web Audio API (No external file needed)
function playGentleChime() {
  if (!soundEnabled.value) return;
  try {
    const AudioCtx = window.AudioContext || window.webkitAudioContext;
    if (!AudioCtx) return;
    const ctx = new AudioCtx();
    
    // Harmonious dual chime (Bell / Ding-Dong)
    const notes = [
      { freq: 587.33, start: 0, dur: 0.6 },    // D5
      { freq: 880.00, start: 0.15, dur: 0.8 }, // A5
      { freq: 1174.66, start: 0.35, dur: 1.2 } // D6
    ];

    notes.forEach(note => {
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.type = 'sine';
      osc.frequency.setValueAtTime(note.freq, ctx.currentTime + note.start);

      gain.gain.setValueAtTime(0, ctx.currentTime + note.start);
      gain.gain.linearRampToValueAtTime(0.2, ctx.currentTime + note.start + 0.04);
      gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + note.start + note.dur);

      osc.connect(gain);
      gain.connect(ctx.destination);

      osc.start(ctx.currentTime + note.start);
      osc.stop(ctx.currentTime + note.start + note.dur + 0.1);
    });
  } catch (err) {
    console.warn('Web Audio not supported or autoplay blocked:', err);
  }
}

// Format milliseconds to MM:SS or HH:MM:SS
function formatTime(ms) {
  const totalSeconds = Math.max(0, Math.floor(Math.abs(ms) / 1000));
  const hrs = Math.floor(totalSeconds / 3600);
  const mins = Math.floor((totalSeconds % 3600) / 60);
  const secs = totalSeconds % 60;

  const mm = String(mins).padStart(2, '0');
  const ss = String(secs).padStart(2, '0');

  if (hrs > 0) {
    const hh = String(hrs).padStart(2, '0');
    return `${hh}:${mm}:${ss}`;
  }
  return `${mm}:${ss}`;
}

// Tick calculations
function tick() {
  if (!isRunning.value) return;

  const now = Date.now();

  if (mode.value === 'countdown') {
    if (isPaused.value) {
      remainingMs.value = pausedRemainingMs.value;
      elapsedMs.value = (targetMinutes.value * 60 * 1000) - pausedRemainingMs.value;
    } else {
      const diff = targetEndTime.value - now;
      remainingMs.value = diff;
      elapsedMs.value = (targetMinutes.value * 60 * 1000) - diff;

      // Play chime when countdown reaches 0
      if (diff <= 0 && !hasChimed) {
        hasChimed = true;
        playGentleChime();
      }
    }
  } else {
    // Stopwatch (Count-up mode)
    if (isPaused.value) {
      elapsedMs.value = pausedRemainingMs.value;
    } else {
      elapsedMs.value = now - startTimestamp.value;
    }
    remainingMs.value = 0;
  }
}

function startTicker() {
  if (!intervalId) {
    intervalId = setInterval(tick, 1000);
  }
  tick();
}

function stopTicker() {
  if (intervalId) {
    clearInterval(intervalId);
    intervalId = null;
  }
}

// Save state to localStorage for persistence
function persistState() {
  const state = {
    isRunning: isRunning.value,
    isPaused: isPaused.value,
    mode: mode.value,
    breakType: breakType.value,
    breakLabel: breakLabel.value,
    targetMinutes: targetMinutes.value,
    startTimestamp: startTimestamp.value,
    targetEndTime: targetEndTime.value,
    pausedRemainingMs: pausedRemainingMs.value,
    hasChimed,
    lastBreakRecord: lastBreakRecord.value
  };
  localStorage.setItem('agent_break_timer_state', JSON.stringify(state));
}

// Load state from localStorage on init
function restoreState() {
  try {
    const raw = localStorage.getItem('agent_break_timer_state');
    if (raw) {
      const state = JSON.parse(raw);
      isRunning.value = !!state.isRunning;
      isPaused.value = !!state.isPaused;
      mode.value = state.mode || 'countdown';
      breakType.value = state.breakType || '15m';
      breakLabel.value = state.breakLabel || 'Short Break';
      targetMinutes.value = state.targetMinutes || 15;
      startTimestamp.value = state.startTimestamp || null;
      targetEndTime.value = state.targetEndTime || null;
      pausedRemainingMs.value = state.pausedRemainingMs || 0;
      hasChimed = !!state.hasChimed;
      lastBreakRecord.value = state.lastBreakRecord || null;

      if (isRunning.value) {
        startTicker();
      }
    }
  } catch (err) {
    console.error('Failed to restore break timer state:', err);
  }
}

// Initial restoration
restoreState();

/**
 * Start a break countdown
 * @param {number} minutes Duration in minutes
 * @param {string} label Name of the break (e.g. 'Istirahat Makan')
 * @param {string} type Preset key ('15m', '30m', '45m', '60m', 'custom')
 */
function startBreak(minutes = 15, label = 'Short Break', type = '15m') {
  const now = Date.now();
  mode.value = 'countdown';
  targetMinutes.value = minutes;
  breakLabel.value = label;
  breakType.value = type;
  startTimestamp.value = now;
  targetEndTime.value = now + (minutes * 60 * 1000);
  isPaused.value = false;
  isRunning.value = true;
  hasChimed = false;
  pausedRemainingMs.value = 0;

  startTicker();
  persistState();
}

/**
 * Start a free stopwatch (count up)
 * @param {string} label Name of the stopwatch
 */
function startStopwatch(label = 'Stopwatch Break') {
  const now = Date.now();
  mode.value = 'stopwatch';
  targetMinutes.value = 0;
  breakLabel.value = label;
  breakType.value = 'stopwatch';
  startTimestamp.value = now;
  targetEndTime.value = null;
  isPaused.value = false;
  isRunning.value = true;
  hasChimed = false;
  pausedRemainingMs.value = 0;

  startTicker();
  persistState();
}

/**
 * Pause running timer
 */
function pauseBreak() {
  if (!isRunning.value || isPaused.value) return;
  const now = Date.now();
  isPaused.value = true;
  if (mode.value === 'countdown') {
    pausedRemainingMs.value = Math.max(0, targetEndTime.value - now);
  } else {
    pausedRemainingMs.value = now - startTimestamp.value;
  }
  persistState();
}

/**
 * Resume paused timer
 */
function resumeBreak() {
  if (!isRunning.value || !isPaused.value) return;
  const now = Date.now();
  isPaused.value = false;
  if (mode.value === 'countdown') {
    targetEndTime.value = now + pausedRemainingMs.value;
    pausedRemainingMs.value = 0;
  } else {
    startTimestamp.value = now - pausedRemainingMs.value;
    pausedRemainingMs.value = 0;
  }
  persistState();
}

/**
 * Finish break and return agent to On Duty
 */
function finishBreak() {
  if (!isRunning.value) return;

  const now = Date.now();
  const actualDurationMs = mode.value === 'countdown'
    ? (isPaused.value ? (targetMinutes.value * 60 * 1000) - pausedRemainingMs.value : now - startTimestamp.value)
    : (isPaused.value ? pausedRemainingMs.value : now - startTimestamp.value);

  const durationMin = Math.round(actualDurationMs / 60000);
  const timeStartStr = new Date(startTimestamp.value).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
  const timeEndStr = new Date(now).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });

  lastBreakRecord.value = {
    label: breakLabel.value,
    type: breakType.value,
    targetMinutes: targetMinutes.value,
    actualDurationFormatted: formatTime(actualDurationMs),
    durationMinutes: durationMin,
    startTime: timeStartStr,
    endTime: timeEndStr,
    date: new Date().toLocaleDateString('id-ID', { day: 'numeric', month: 'short' }),
    wasOvertime: mode.value === 'countdown' && actualDurationMs > (targetMinutes.value * 60 * 1000)
  };

  isRunning.value = false;
  isPaused.value = false;
  hasChimed = false;
  stopTicker();
  persistState();
}

/**
 * Reset / cancel current break
 */
function cancelBreak() {
  isRunning.value = false;
  isPaused.value = false;
  hasChimed = false;
  stopTicker();
  persistState();
}

function toggleSound() {
  soundEnabled.value = !soundEnabled.value;
  localStorage.setItem('agent_break_sound', String(soundEnabled.value));
}

// Computed displays
const formattedRemaining = computed(() => {
  if (mode.value === 'stopwatch') {
    return formatTime(elapsedMs.value);
  }
  return formatTime(remainingMs.value);
});

const isOvertime = computed(() => {
  return mode.value === 'countdown' && isRunning.value && remainingMs.value < 0;
});

const overtimeFormatted = computed(() => {
  if (!isOvertime.value) return '';
  return '+' + formatTime(Math.abs(remainingMs.value));
});

const formattedElapsed = computed(() => {
  return formatTime(elapsedMs.value);
});

// Progress percentage (0 to 100)
const progressPercent = computed(() => {
  if (mode.value === 'stopwatch') return 100;
  if (!targetMinutes.value) return 0;
  const totalMs = targetMinutes.value * 60 * 1000;
  if (totalMs <= 0) return 0;
  const p = (elapsedMs.value / totalMs) * 100;
  return Math.min(100, Math.max(0, Math.round(p)));
});

// Urgency badge: 'normal' | 'warning' (< 2 mins) | 'overtime'
const urgencyStatus = computed(() => {
  if (!isRunning.value) return 'idle';
  if (mode.value === 'stopwatch') return 'stopwatch';
  if (isOvertime.value) return 'overtime';
  if (remainingMs.value <= 2 * 60 * 1000) return 'warning';
  return 'normal';
});

// Short string suitable for browser tab title
const titleSnippet = computed(() => {
  if (!isRunning.value) return '';
  if (mode.value === 'stopwatch') {
    return `${formattedElapsed.value} Break`;
  }
  if (isOvertime.value) {
    return `${overtimeFormatted.value} Overtime`;
  }
  return `${formattedRemaining.value} Sisa Break`;
});

export function useAgentBreakTimer() {
  return {
    // State
    isRunning,
    isPaused,
    mode,
    breakType,
    breakLabel,
    targetMinutes,
    remainingMs,
    elapsedMs,
    soundEnabled,
    lastBreakRecord,

    // Computeds
    formattedRemaining,
    formattedElapsed,
    isOvertime,
    overtimeFormatted,
    progressPercent,
    urgencyStatus,
    titleSnippet,

    // Methods
    startBreak,
    startStopwatch,
    pauseBreak,
    resumeBreak,
    finishBreak,
    cancelBreak,
    toggleSound,
    playGentleChime,
    formatTime
  };
}
