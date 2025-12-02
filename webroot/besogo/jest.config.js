module.exports = {
  testEnvironment: 'jsdom',
  testMatch: ['**/tests/**/*.test.js'],
  verbose: true,
  // Set up globals that besogo expects
  setupFilesAfterEnv: ['<rootDir>/tests/setup.js'],
};
