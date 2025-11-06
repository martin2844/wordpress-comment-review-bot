/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./plugin/**/*.php",
    "./src/**/*.js",
    "./src/**/*.css"
  ],
  theme: {
    extend: {
      colors: {
        'wp-blue': '#007cba',
        'wp-dark': '#1e293b',
        'wp-gray': '#64748b'
      },
      fontFamily: {
        'wp': ['-apple-system', 'BlinkMacSystemFont', '"Segoe UI"', 'Roboto', 'sans-serif']
      }
    },
  },
  plugins: [],
}