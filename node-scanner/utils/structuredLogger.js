const fs = require('fs');
const path = require('path');

const LEVELS = {
  error: 0,
  warn: 1,
  info: 2,
  debug: 3,
};

function normalizeLevel(level) {
  const normalized = String(level || '').toLowerCase();
  return Object.prototype.hasOwnProperty.call(LEVELS, normalized) ? normalized : 'info';
}

function createStructuredLogger(options = {}) {
  const logLevel = normalizeLevel(options.level || process.env.SCAN_LOG_LEVEL || 'info');
  const logThreshold = LEVELS[logLevel];
  const stdout = options.stdout || process.stdout;
  const stderr = options.stderr || process.stderr;
  const logFilePath = options.logFilePath || path.resolve(__dirname, '..', '..', 'storage', 'logs', 'node-scanner.log');
  const baseFields = options.baseFields || {};

  const getOutputForLevel = (level) => (level === 'error' ? stderr : stdout);

  const writeLine = (line, level) => {
    const output = getOutputForLevel(level);

    if (output && typeof output.write === 'function') {
      output.write(`${line}\n`);
    }

    try {
      fs.mkdirSync(path.dirname(logFilePath), { recursive: true });
      fs.appendFileSync(logFilePath, `${line}\n`);
    } catch {
      // noop
    }
  };

  const emit = (level, message, fields = {}) => {
    if (LEVELS[level] > logThreshold) {
      return;
    }

    const payload = {
      time: new Date().toISOString(),
      level,
      ...baseFields,
      ...fields,
    };

    if (message !== undefined && message !== null && payload.type !== message) {
      payload.message = message;
    }

    writeLine(JSON.stringify(payload), level);
  };

  const logger = {
    error: (message, fields) => emit('error', message, fields),
    warn: (message, fields) => emit('warn', message, fields),
    info: (message, fields) => emit('info', message, fields),
    debug: (message, fields) => emit('debug', message, fields),
    child: (childFields = {}) =>
      createStructuredLogger({
        level: logLevel,
        stdout,
        stderr,
        logFilePath,
        baseFields: {
          ...baseFields,
          ...childFields,
        },
      }),
  };

  return logger;
}

const defaultLogger = createStructuredLogger();

module.exports = {
  createStructuredLogger,
  normalizeLevel,
  info: (...args) => defaultLogger.info(...args),
  warn: (...args) => defaultLogger.warn(...args),
  error: (...args) => defaultLogger.error(...args),
  debug: (...args) => defaultLogger.debug(...args),
};
