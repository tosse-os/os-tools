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
  const logFilePathResolver = options.logFilePathResolver || (() => {
    const date = new Date().toISOString().slice(0, 10);
    return path.resolve(__dirname, '..', '..', 'storage', 'logs', `node-scanner-${date}.log`);
  });
  const baseFields = options.baseFields || {};

  const writeLine = (line, level) => {
    const output = stdout;
    const logFilePath = logFilePathResolver(level);

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
        logFilePathResolver,
        baseFields: {
          ...baseFields,
          ...childFields,
        },
      }),
  };

  return logger;
}

module.exports = {
  createStructuredLogger,
  normalizeLevel,
};
