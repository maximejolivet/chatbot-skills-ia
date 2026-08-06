module.exports = {
  extends: ['@commitlint/config-conventional'],
  rules: {
    'scope-empty': [2, 'never'],
    'type-enum': [
      2,
      'always',
      ['feat', 'fix', 'refactor', 'perf', 'docs', 'style', 'test', 'build', 'ci', 'chore', 'revert', 'security'],
    ],
  },
};
