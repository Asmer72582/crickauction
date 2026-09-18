/**
 * Cricket Theme System
 * Multiple broadcast themes for different tournaments and match formats
 */

const THEMES = {
  classic: {
    id: 'classic',
    name: 'Classic Broadcast',
    description: 'Traditional TV broadcast style',
    category: 'broadcast',
    colors: {
      primary: '#1a3a5c',
      secondary: '#0a1628',
      accent: '#d4af37',
      teamA: ['#c41e3a', '#8b0000'],
      teamB: ['#1e90d8', '#1565a8'],
      background: 'linear-gradient(180deg, rgba(8, 18, 38, 0.97) 0%, rgba(12, 28, 58, 0.95) 100%)',
      border: 'rgba(100, 160, 220, 0.25)',
      textPrimary: '#fff',
      textSecondary: 'rgba(200, 220, 245, 0.85)',
      runColor: '#2dd4bf',
      sixColor: '#c084fc',
      wicketColor: '#ef4444',
      milestoneColor: '#f5d000',
      chaseBg: 'rgba(30, 100, 180, 0.35)',
      resultBg: 'linear-gradient(90deg, rgba(15, 35, 65, 0.95), rgba(25, 55, 95, 0.9))',
      footerGradient: 'linear-gradient(90deg, #c9a227 0%, #4ade80 50%, #c9a227 100%)',
    },
    fonts: {
      heading: '"Oswald", sans-serif',
      body: '"Roboto Condensed", "Segoe UI", sans-serif',
    },
    layout: 'summary-card',
    animations: {
      four: { color: '#2dd4bf', label: 'FOUR!' },
      six: { color: '#c084fc', label: 'SIX!' },
      wicket: { color: '#ef4444', label: 'WICKET!' },
      out: { color: '#dc2626', label: 'OUT!' },
      milestone: { color: '#f5d000', label: 'MILESTONE!' },
    },
    borderRadius: '4px',
    cardWidth: '1480px',
  },

  ipl: {
    id: 'ipl',
    name: 'IPL 2025',
    description: 'Indian Premier League vibrant theme',
    category: 'tournament',
    colors: {
      primary: '#003399',
      secondary: '#001a4d',
      accent: '#FFD700',
      teamA: ['#003399', '#001a4d'],
      teamB: ['#C8102E', '#8B0000'],
      background: 'linear-gradient(135deg, #001a4d 0%, #003399 50%, #001a4d 100%)',
      border: 'rgba(255, 215, 0, 0.4)',
      textPrimary: '#fff',
      textSecondary: 'rgba(255, 215, 0, 0.9)',
      runColor: '#00FF7F',
      sixColor: '#FFD700',
      wicketColor: '#FF4444',
      milestoneColor: '#FFD700',
      chaseBg: 'rgba(0, 51, 153, 0.5)',
      resultBg: 'linear-gradient(90deg, #001a4d, #003399)',
      footerGradient: 'linear-gradient(90deg, #FFD700 0%, #FFA500 50%, #FFD700 100%)',
    },
    fonts: {
      heading: '"Oswald", sans-serif',
      body: '"Rajdhani", "Segoe UI", sans-serif',
    },
    layout: 'ipl-card',
    animations: {
      four: { color: '#00FF7F', label: 'FOUR!' },
      six: { color: '#FFD700', label: 'SIX!' },
      wicket: { color: '#FF4444', label: 'WICKET!' },
      out: { color: '#FF4444', label: 'OUT!' },
      milestone: { color: '#FFD700', label: 'MILESTONE!' },
    },
    borderRadius: '8px',
    cardWidth: '1520px',
    logo: '🏏',
    tournamentName: 'TATA IPL 2025',
  },

  t20wc: {
    id: 't20wc',
    name: 'T20 World Cup',
    description: 'ICC T20 World Cup official style',
    category: 'tournament',
    colors: {
      primary: '#002B5C',
      secondary: '#001838',
      accent: '#00A8E8',
      teamA: ['#002B5C', '#001838'],
      teamB: ['#C8102E', '#8B0000'],
      background: 'linear-gradient(180deg, #001838 0%, #002B5C 100%)',
      border: 'rgba(0, 168, 232, 0.4)',
      textPrimary: '#fff',
      textSecondary: 'rgba(0, 168, 232, 0.9)',
      runColor: '#00E676',
      sixColor: '#FFD600',
      wicketColor: '#FF1744',
      milestoneColor: '#FFD600',
      chaseBg: 'rgba(0, 43, 92, 0.6)',
      resultBg: 'linear-gradient(90deg, #001838, #002B5C)',
      footerGradient: 'linear-gradient(90deg, #00A8E8 0%, #00E676 50%, #FFD600 100%)',
    },
    fonts: {
      heading: '"Oswald", sans-serif',
      body: '"Roboto", sans-serif',
    },
    layout: 'modern-card',
    animations: {
      four: { color: '#00E676', label: 'FOUR!' },
      six: { color: '#FFD600', label: 'SIX!' },
      wicket: { color: '#FF1744', label: 'WICKET!' },
      out: { color: '#FF1744', label: 'OUT!' },
      milestone: { color: '#FFD600', label: 'MILESTONE!' },
    },
    borderRadius: '6px',
    cardWidth: '1500px',
    logo: '🏆',
    tournamentName: 'ICC T20 WORLD CUP',
  },

  test: {
    id: 'test',
    name: 'Test Championship',
    description: 'Traditional Test match whites',
    category: 'format',
    colors: {
      primary: '#1a1a2e',
      secondary: '#0f0f1a',
      accent: '#C8A050',
      teamA: ['#2C3E50', '#1a252f'],
      teamB: ['#8B0000', '#5a0000'],
      background: 'linear-gradient(180deg, #f5f5f0 0%, #e8e8e0 100%)',
      border: 'rgba(200, 160, 80, 0.3)',
      textPrimary: '#1a1a2e',
      textSecondary: '#4a4a4a',
      runColor: '#2C3E50',
      sixColor: '#C8A050',
      wicketColor: '#8B0000',
      milestoneColor: '#C8A050',
      chaseBg: 'rgba(44, 62, 80, 0.15)',
      resultBg: 'linear-gradient(90deg, #1a1a2e, #2C3E50)',
      footerGradient: 'linear-gradient(90deg, #C8A050 0%, #D4B870 50%, #C8A050 100%)',
    },
    fonts: {
      heading: '"Merriweather", serif',
      body: '"Source Serif Pro", Georgia, serif',
    },
    layout: 'test-card',
    animations: {
      four: { color: '#2C3E50', label: 'FOUR' },
      six: { color: '#C8A050', label: 'SIX' },
      wicket: { color: '#8B0000', label: 'WICKET' },
      out: { color: '#8B0000', label: 'OUT' },
      milestone: { color: '#C8A050', label: 'MILESTONE' },
    },
    borderRadius: '2px',
    cardWidth: '1440px',
    logo: '🏏',
    tournamentName: 'WORLD TEST CHAMPIONSHIP',
  },

  thehundred: {
    id: 'thehundred',
    name: 'The Hundred',
    description: 'The Hundred competition modern style',
    category: 'tournament',
    colors: {
      primary: '#000000',
      secondary: '#1a1a1a',
      accent: '#FF6B35',
      teamA: ['#000000', '#1a1a1a'],
      teamB: ['#FF6B35', '#CC5228'],
      background: 'linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 50%, #0a0a0a 100%)',
      border: 'rgba(255, 107, 53, 0.4)',
      textPrimary: '#fff',
      textSecondary: 'rgba(255, 107, 53, 0.9)',
      runColor: '#00FF88',
      sixColor: '#FF6B35',
      wicketColor: '#FF3366',
      milestoneColor: '#FFD700',
      chaseBg: 'rgba(255, 107, 53, 0.2)',
      resultBg: 'linear-gradient(90deg, #000000, #1a1a1a)',
      footerGradient: 'linear-gradient(90deg, #FF6B35 0%, #FF3366 50%, #00FF88 100%)',
    },
    fonts: {
      heading: '"Montserrat", sans-serif',
      body: '"Inter", sans-serif',
    },
    layout: 'modern-card',
    animations: {
      four: { color: '#00FF88', label: '4' },
      six: { color: '#FF6B35', label: '6' },
      wicket: { color: '#FF3366', label: 'W' },
      out: { color: '#FF3366', label: 'OUT' },
      milestone: { color: '#FFD700', label: '100' },
    },
    borderRadius: '12px',
    cardWidth: '1480px',
    logo: '100',
    tournamentName: 'THE HUNDRED',
  },

  bigbash: {
    id: 'bigbash',
    name: 'Big Bash League',
    description: 'BBL vibrant pink and blue',
    category: 'tournament',
    colors: {
      primary: '#E91E63',
      secondary: '#880E4F',
      accent: '#00BCD4',
      teamA: ['#E91E63', '#880E4F'],
      teamB: ['#00BCD4', '#00838F'],
      background: 'linear-gradient(135deg, #880E4F 0%, #E91E63 50%, #00BCD4 100%)',
      border: 'rgba(255, 255, 255, 0.3)',
      textPrimary: '#fff',
      textSecondary: 'rgba(255, 255, 255, 0.9)',
      runColor: '#00E676',
      sixColor: '#FFD600',
      wicketColor: '#FF1744',
      milestoneColor: '#FFD600',
      chaseBg: 'rgba(233, 30, 99, 0.4)',
      resultBg: 'linear-gradient(90deg, #880E4F, #E91E63)',
      footerGradient: 'linear-gradient(90deg, #FF6B35 0%, #FF3366 50%, #00FF88 100%)',
    },
    fonts: {
      heading: '"Bebas Neue", sans-serif',
      body: '"Roboto", sans-serif',
    },
    layout: 'modern-card',
    animations: {
      four: { color: '#00E676', label: 'FOUR!' },
      six: { color: '#FFD600', label: 'SIX!' },
      wicket: { color: '#FF1744', label: 'WICKET!' },
      out: { color: '#FF1744', label: 'OUT!' },
      milestone: { color: '#FFD600', label: 'MILESTONE!' },
    },
    borderRadius: '10px',
    cardWidth: '1500px',
    logo: '⚡',
    tournamentName: 'KFC BIG BASH LEAGUE',
  },

  cpl: {
    id: 'cpl',
    name: 'Caribbean Premier League',
    description: 'CPL vibrant Caribbean colors',
    category: 'tournament',
    colors: {
      primary: '#FF6B00',
      secondary: '#CC5500',
      accent: '#00FF88',
      teamA: ['#FF6B00', '#CC5500'],
      teamB: ['#004B87', '#003366'],
      background: 'linear-gradient(135deg, #FF6B00 0%, #FF8C00 30%, #004B87 70%, #003366 100%)',
      border: 'rgba(255, 107, 0, 0.5)',
      textPrimary: '#fff',
      textSecondary: 'rgba(255, 255, 255, 0.9)',
      runColor: '#00FF88',
      sixColor: '#FFD600',
      wicketColor: '#FF1744',
      milestoneColor: '#FFD600',
      chaseBg: 'rgba(255, 107, 0, 0.4)',
      resultBg: 'linear-gradient(90deg, #CC5500, #FF6B00)',
      footerGradient: 'linear-gradient(90deg, #FF6B00 0%, #00FF88 50%, #004B87 100%)',
    },
    fonts: {
      heading: '"Luckiest Guy", cursive',
      body: '"Nunito", sans-serif',
    },
    layout: 'modern-card',
    animations: {
      four: { color: '#00FF88', label: 'FOUR!' },
      six: { color: '#FFD600', label: 'SIX!' },
      wicket: { color: '#FF1744', label: 'WICKET!' },
      out: { color: '#FF1744', label: 'OUT!' },
      milestone: { color: '#FFD600', label: 'MILESTONE!' },
    },
    borderRadius: '15px',
    cardWidth: '1520px',
    logo: '🌴',
    tournamentName: 'REPUBLIC BANK CPL',
  },

  psl: {
    id: 'psl',
    name: 'Pakistan Super League',
    description: 'PSL green and gold theme',
    category: 'tournament',
    colors: {
      primary: '#006400',
      secondary: '#004d00',
      accent: '#FFD700',
      teamA: ['#006400', '#004d00'],
      teamB: ['#FFD700', '#B8860B'],
      background: 'linear-gradient(180deg, #002b00 0%, #006400 50%, #004d00 100%)',
      border: 'rgba(255, 215, 0, 0.5)',
      textPrimary: '#fff',
      textSecondary: 'rgba(255, 215, 0, 0.9)',
      runColor: '#00FF7F',
      sixColor: '#FFD700',
      wicketColor: '#FF4444',
      milestoneColor: '#FFD700',
      chaseBg: 'rgba(0, 100, 0, 0.5)',
      resultBg: 'linear-gradient(90deg, #004d00, #006400)',
      footerGradient: 'linear-gradient(90deg, #FFD700 0%, #FFA500 50%, #FFD700 100%)',
    },
    fonts: {
      heading: '"Oswald", sans-serif',
      body: '"Roboto", sans-serif',
    },
    layout: 'modern-card',
    animations: {
      four: { color: '#00FF7F', label: 'FOUR!' },
      six: { color: '#FFD700', label: 'SIX!' },
      wicket: { color: '#FF4444', label: 'WICKET!' },
      out: { color: '#FF4444', label: 'OUT!' },
      milestone: { color: '#FFD700', label: 'MILESTONE!' },
    },
    borderRadius: '8px',
    cardWidth: '1500px',
    logo: '🏏',
    tournamentName: 'HBL PAKISTAN SUPER LEAGUE',
  },

  wpl: {
    id: 'wpl',
    name: "Women's Premier League",
    description: 'WPL purple and pink theme',
    category: 'tournament',
    colors: {
      primary: '#6A0DAD',
      secondary: '#4B0082',
      accent: '#FF69B4',
      teamA: ['#6A0DAD', '#4B0082'],
      teamB: ['#FF69B4', '#FF1493'],
      background: 'linear-gradient(135deg, #4B0082 0%, #6A0DAD 50%, #FF69B4 100%)',
      border: 'rgba(255, 105, 180, 0.5)',
      textPrimary: '#fff',
      textSecondary: 'rgba(255, 105, 180, 0.9)',
      runColor: '#00FF88',
      sixColor: '#FFD700',
      wicketColor: '#FF4444',
      milestoneColor: '#FFD700',
      chaseBg: 'rgba(106, 13, 173, 0.5)',
      resultBg: 'linear-gradient(90deg, #4B0082, #6A0DAD)',
      footerGradient: 'linear-gradient(90deg, #FF69B4 0%, #FFD700 50%, #6A0DAD 100%)',
    },
    fonts: {
      heading: '"Poppins", sans-serif',
      body: '"Inter", sans-serif',
    },
    layout: 'modern-card',
    animations: {
      four: { color: '#00FF88', label: 'FOUR!' },
      six: { color: '#FFD700', label: 'SIX!' },
      wicket: { color: '#FF4444', label: 'WICKET!' },
      out: { color: '#FF4444', label: 'OUT!' },
      milestone: { color: '#FFD700', label: 'MILESTONE!' },
    },
    borderRadius: '12px',
    cardWidth: '1480px',
    logo: '💜',
    tournamentName: 'TATA WPL',
  },

  county: {
    id: 'county',
    name: 'County Championship',
    description: 'English County traditional style',
    category: 'format',
    colors: {
      primary: '#003366',
      secondary: '#001f3d',
      accent: '#C8A050',
      teamA: ['#003366', '#001f3d'],
      teamB: ['#8B0000', '#5a0000'],
      background: 'linear-gradient(180deg, #f0f4f8 0%, #dce4ec 100%)',
      border: 'rgba(0, 51, 102, 0.3)',
      textPrimary: '#003366',
      textSecondary: '#4a5a6a',
      runColor: '#003366',
      sixColor: '#C8A050',
      wicketColor: '#8B0000',
      milestoneColor: '#C8A050',
      chaseBg: 'rgba(0, 51, 102, 0.15)',
      resultBg: 'linear-gradient(90deg, #003366, #001f3d)',
      footerGradient: 'linear-gradient(90deg, #C8A050 0%, #D4B870 50%, #C8A050 100%)',
    },
    fonts: {
      heading: '"Merriweather", serif',
      body: '"Source Serif Pro", Georgia, serif',
    },
    layout: 'test-card',
    animations: {
      four: { color: '#003366', label: 'FOUR' },
      six: { color: '#C8A050', label: 'SIX' },
      wicket: { color: '#8B0000', label: 'WICKET' },
      out: { color: '#8B0000', label: 'OUT' },
      milestone: { color: '#C8A050', label: 'MILESTONE' },
    },
    borderRadius: '2px',
    cardWidth: '1440px',
    logo: '🏏',
    tournamentName: 'COUNTY CHAMPIONSHIP',
  },
};

const THEME_CATEGORIES = {
  broadcast: { name: 'Broadcast Styles', themes: ['classic'] },
  tournament: { name: 'Tournaments', themes: ['ipl', 't20wc', 'thehundred', 'bigbash', 'cpl', 'psl', 'wpl'] },
  format: { name: 'Match Formats', themes: ['test', 'county'] },
};

function getTheme(themeId) {
  return THEMES[themeId] || THEMES.classic;
}

function getAllThemes() {
  return Object.values(THEMES);
}

function getThemesByCategory(category) {
  const cat = THEME_CATEGORIES[category];
  if (!cat) return [];
  return cat.themes.map(id => THEMES[id]).filter(Boolean);
}

function applyThemeToDocument(theme) {
  const root = document.documentElement;
  const colors = theme.colors;

  root.style.setProperty('--theme-primary', colors.primary);
  root.style.setProperty('--theme-secondary', colors.secondary);
  root.style.setProperty('--theme-accent', colors.accent);
  root.style.setProperty('--theme-teamA-1', colors.teamA[0]);
  root.style.setProperty('--theme-teamA-2', colors.teamA[1]);
  root.style.setProperty('--theme-teamB-1', colors.teamB[0]);
  root.style.setProperty('--theme-teamB-2', colors.teamB[1]);
  root.style.setProperty('--theme-background', colors.background);
  root.style.setProperty('--theme-border', colors.border);
  root.style.setProperty('--theme-text-primary', colors.textPrimary);
  root.style.setProperty('--theme-text-secondary', colors.textSecondary);
  root.style.setProperty('--theme-run-color', colors.runColor);
  root.style.setProperty('--theme-six-color', colors.sixColor);
  root.style.setProperty('--theme-wicket-color', colors.wicketColor);
  root.style.setProperty('--theme-milestone-color', colors.milestoneColor);
  root.style.setProperty('--theme-chase-bg', colors.chaseBg);
  root.style.setProperty('--theme-result-bg', colors.resultBg);
  root.style.setProperty('--theme-footer-gradient', colors.footerGradient);
  root.style.setProperty('--theme-font-heading', theme.fonts.heading);
  root.style.setProperty('--theme-font-body', theme.fonts.body);
  root.style.setProperty('--theme-border-radius', theme.borderRadius);
  root.style.setProperty('--theme-card-width', theme.cardWidth);
}

if (typeof module !== 'undefined' && module.exports) {
  module.exports = { THEMES, THEME_CATEGORIES, getTheme, getAllThemes, getThemesByCategory, applyThemeToDocument };
}