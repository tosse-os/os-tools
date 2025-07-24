module.exports = {
  content: [
    './resources/views/**/*.blade.php',
    './resources/js/**/*.js',
    './resources/css/**/*.css',
  ],
  safelist: [
    'bg-green-500',
    'bg-red-500',
    'bg-gray-400',
    'text-green-600',
    'text-red-600',
    'text-orange-600',
    'font-semibold',
    'rounded-full',
    'w-3',
    'h-3',
    'w-4',
    'h-4',
  ],
  theme: {
    extend: {},
  },
  plugins: [],
}
