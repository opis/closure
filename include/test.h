#define ZEND_API
#define ZEND_FASTCALL
#define ZEND_MAX_RESERVED_RESOURCES 6

#include <cstddef>
#include <cstdint>
#include <cstdarg>

#ifndef ssize_t
#if defined(_WIN64)
#define ssize_t __int64
#elif defined(_WIN32)
#define ssize_t __int32
#elif defined(__GNUC__) && __GNUC__ >= 4
#define ssize_t long
#endif
#endif

#include "headers.h"

// g++ -Wall -c test.h -o /dev/null




