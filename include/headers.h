#define FFI_SCOPE "FFI_SCOPE_NAME"
#define FFI_LIB "FFI_LIB_NAME"

typedef int ts_rsrc_id;
typedef int32_t zend_long;
typedef uint32_t zend_ulong;
typedef uintptr_t zend_type;
typedef unsigned char zend_bool;
typedef unsigned char zend_uchar;

typedef struct _zval_struct     zval;
typedef struct _zend_refcounted zend_refcounted;

typedef struct _zend_string     zend_string;
typedef struct _zend_array      zend_array;
typedef struct _zend_object     zend_object;
typedef union  _zend_function   zend_function;
typedef struct _zend_resource   zend_resource;
typedef struct _zend_reference  zend_reference;
typedef struct _zend_ast_ref    zend_ast_ref;
typedef struct _zend_execute_data    zend_execute_data;
typedef struct _zend_array      HashTable;

typedef struct _zend_class_entry     zend_class_entry;
typedef struct _zend_module_entry    zend_module_entry;
typedef struct _zend_module_dep      zend_module_dep;
typedef struct _zend_ini_entry       zend_ini_entry;

typedef struct _zend_object_handlers zend_object_handlers;

typedef struct _zend_executor_globals zend_executor_globals;
typedef struct _zend_op_array zend_op_array;
typedef struct _zend_op zend_op;

typedef union _zend_value {
    zend_long         lval;				/* long value */
    double            dval;				/* double value */
    zend_refcounted  *counted;
    zend_string      *str;
    zend_array       *arr;
    zend_object      *obj;
    zend_resource    *res;
    zend_reference   *ref;
    zend_ast_ref     *ast;
    zval             *zv;
    void             *ptr;
    zend_class_entry *ce;
    zend_function    *func;
    struct {
        uint32_t w1;
        uint32_t w2;
    } ww;
} zend_value;

struct _zval_struct {
    zend_value        value;			/* value */
    union {
        uint32_t type_info;
        struct {
            zend_uchar    type;            /* active type */
            zend_uchar    type_flags;
            union {
                uint16_t  extra;        /* not further specified */
            } u;
        } v;
    } u1;
    union {
        uint32_t     next;                 /* hash collision chain */
        uint32_t     cache_slot;           /* cache slot (for RECV_INIT) */
        uint32_t     opline_num;           /* opline number (for FAST_CALL) */
        uint32_t     lineno;               /* line number (for ast nodes) */
        uint32_t     num_args;             /* arguments number for EX(This) */
        uint32_t     fe_pos;               /* foreach position */
        uint32_t     fe_iter_idx;          /* foreach iterator index */
        uint32_t     access_flags;         /* class constant access flags */
        uint32_t     property_guard;       /* single property guard */
        uint32_t     constant_flags;       /* constant flags */
        uint32_t     extra;                /* not further specified */
    } u2;
};
typedef struct _zend_refcounted_h {
    uint32_t         refcount;			/* reference counter 32-bit */
    union {
        uint32_t type_info;
    } u;
} zend_refcounted_h;
struct _zend_refcounted {

    zend_refcounted_h gc;
};

typedef struct _Bucket {
    zval              val;
    zend_ulong        h;                /* hash value (or numeric index)   */
    zend_string      *key;              /* string key or NULL for numerics */
} Bucket;
typedef void (*dtor_func_t)(zval *pDest);
struct _zend_array {
    zend_refcounted_h gc;
    union {
        struct {
            zend_uchar    flags;
            zend_uchar    _unused;
            zend_uchar    nIteratorsCount;
            zend_uchar    _unused2;
        } v;
        uint32_t flags;
    } u;
    uint32_t          nTableMask;
    Bucket           *arData;
    uint32_t          nNumUsed;
    uint32_t          nNumOfElements;
    uint32_t          nTableSize;
    uint32_t          nInternalPointer;
    zend_long         nNextFreeElement;
    dtor_func_t       pDestructor;
};
struct _zend_string {
    zend_refcounted_h gc;
    zend_ulong        h;                /* hash value */
    size_t            len;
    char              val[1];
};

struct _zend_object {
    zend_refcounted_h gc;
    uint32_t          handle; // TODO: may be removed ???
    zend_class_entry *ce;
    const zend_object_handlers *handlers;
    HashTable        *properties;
    zval              properties_table[1];
};

typedef struct _zend_property_info {
	uint32_t offset; /* property offset for object properties or
	                      property index for static properties */
	uint32_t flags;
	zend_string *name;
	zend_string *doc_comment;
	zend_class_entry *ce;
	zend_type type;
} zend_property_info;
typedef struct _zend_class_iterator_funcs {
	zend_function *zf_new_iterator;
	zend_function *zf_valid;
	zend_function *zf_current;
	zend_function *zf_key;
	zend_function *zf_next;
	zend_function *zf_rewind;
} zend_class_iterator_funcs;
typedef struct _zend_object_iterator zend_object_iterator;
typedef struct _zend_serialize_data zend_serialize_data;
typedef struct _zend_unserialize_data zend_unserialize_data;

struct _zend_execute_data {
	const zend_op       *opline;           /* executed opline                */
	zend_execute_data   *call;             /* current call                   */
	zval                *return_value;
	zend_function       *func;             /* executed function              */
	zval                 This;             /* this + call_info + num_args    */
	zend_execute_data   *prev_execute_data;
	zend_array          *symbol_table;
	void               **run_time_cache;   /* cache op_array->run_time_cache */
};

typedef struct _zend_object_iterator_funcs {
	/* release all resources associated with this iterator instance */
	void (*dtor)(zend_object_iterator *iter);

	/* check for end of iteration (FAILURE or SUCCESS if data is valid) */
	int (*valid)(zend_object_iterator *iter);

	/* fetch the item data for the current element */
	zval *(*get_current_data)(zend_object_iterator *iter);

	/* fetch the key for the current element (optional, may be NULL). The key
	 * should be written into the provided zval* using the ZVAL_* macros. If
	 * this handler is not provided auto-incrementing integer keys will be
	 * used. */
	void (*get_current_key)(zend_object_iterator *iter, zval *key);

	/* step forwards to next element */
	void (*move_forward)(zend_object_iterator *iter);

	/* rewind to start of data (optional, may be NULL) */
	void (*rewind)(zend_object_iterator *iter);

	/* invalidate current value/key (optional, may be NULL) */
	void (*invalidate_current)(zend_object_iterator *iter);
} zend_object_iterator_funcs;

struct _zend_serialize_data;
struct _zend_unserialize_data;
struct _zend_module_dep {
	const char *name;		/* module name */
	const char *rel;		/* version relationship: NULL (exists), lt|le|eq|ge|gt (to given version) */
	const char *version;	/* version */
	unsigned char type;		/* dependency type */
};
struct _zend_ini_entry {
	zend_string *name;
	int (*on_modify)(zend_ini_entry *entry, zend_string *new_value, void *mh_arg1, void *mh_arg2, void *mh_arg3, int stage);
	void *mh_arg1;
	void *mh_arg2;
	void *mh_arg3;
	zend_string *value;
	zend_string *orig_value;
	void (*displayer)(zend_ini_entry *ini_entry, int type);

	int module_number;

	uint8_t modifiable;
	uint8_t orig_modifiable;
	uint8_t modified;
};

struct _zend_object_iterator {
	zend_object std;
	zval data;
	const zend_object_iterator_funcs *funcs;
	zend_ulong index; /* private to fe_reset/fe_fetch opcodes */
};

typedef struct _zend_class_name {
	zend_string *name;
	zend_string *lc_name;
} zend_class_name;
typedef struct _zend_trait_method_reference {
	zend_string *method_name;
	zend_string *class_name;
} zend_trait_method_reference;
typedef struct _zend_trait_alias {
	zend_trait_method_reference trait_method;

	/**
	* name for method to be added
	*/
	zend_string *alias;

	/**
	* modifiers to be set on trait method
	*/
	uint32_t modifiers;
} zend_trait_alias;
typedef struct _zend_trait_precedence {
	zend_trait_method_reference trait_method;
	uint32_t num_excludes;
	zend_string *exclude_class_names[1];
} zend_trait_precedence;

typedef void (ZEND_FASTCALL *zif_handler)(zend_execute_data *execute_data, zval *return_value);
typedef struct _zend_internal_arg_info {
	const char *name;
	zend_type type;
	zend_uchar pass_by_reference;
	zend_bool is_variadic;
} zend_internal_arg_info;
typedef struct _zend_function_entry {
	const char *fname;
	zif_handler handler;
	const struct _zend_internal_arg_info *arg_info;
	uint32_t num_args;
	uint32_t flags;
} zend_function_entry;

struct _zend_module_entry {
	unsigned short size;
	unsigned int zend_api;
	unsigned char zend_debug;
	unsigned char zts;
	const struct _zend_ini_entry *ini_entry;
	const struct _zend_module_dep *deps;
	const char *name;
	const struct _zend_function_entry *functions;
	int (*module_startup_func)(int type, int module_number);
	int (*module_shutdown_func)(int type, int module_number);
	int (*request_startup_func)(int type, int module_number);
	int (*request_shutdown_func)(int type, int module_number);
	void (*info_func)(zend_module_entry *zend_module);
	const char *version;
	size_t globals_size;
#ifdef ZTS
	ts_rsrc_id* globals_id_ptr;
#else
	void* globals_ptr;
#endif
	void (*globals_ctor)(void *global);
	void (*globals_dtor)(void *global);
	int (*post_deactivate_func)(void);
	int module_started;
	unsigned char type;
	void *handle;
	int module_number;
	const char *build_id;
};

struct _zend_class_entry {
	char type;
	zend_string *name;
	/* class_entry or string depending on ZEND_ACC_LINKED */
	union {
		zend_class_entry *parent;
		zend_string *parent_name;
	};
	int refcount;
	uint32_t ce_flags;

	int default_properties_count;
	int default_static_members_count;
	zval *default_properties_table;
	zval *default_static_members_table;
	zval ** static_members_table;
	HashTable function_table;
	HashTable properties_info;
	HashTable constants_table;

	struct _zend_property_info **properties_info_table;

	zend_function *constructor;
	zend_function *destructor;
	zend_function *clone;
	zend_function *__get;
	zend_function *__set;
	zend_function *__unset;
	zend_function *__isset;
	zend_function *__call;
	zend_function *__callstatic;
	zend_function *__tostring;
	zend_function *__debugInfo;
	zend_function *serialize_func;
	zend_function *unserialize_func;

	/* allocated only if class implements Iterator or IteratorAggregate interface */
	zend_class_iterator_funcs *iterator_funcs_ptr;

	/* handlers */
	union {
		zend_object* (*create_object)(zend_class_entry *class_type);
		int (*interface_gets_implemented)(zend_class_entry *iface, zend_class_entry *class_type); /* a class implements this interface */
	};
	zend_object_iterator *(*get_iterator)(zend_class_entry *ce, zval *object, int by_ref);
	zend_function *(*get_static_method)(zend_class_entry *ce, zend_string* method);

	/* serializer callbacks */
	int (*serialize)(zval *object, unsigned char **buffer, size_t *buf_len, zend_serialize_data *data);
	int (*unserialize)(zval *object, zend_class_entry *ce, const unsigned char *buf, size_t buf_len, zend_unserialize_data *data);

	uint32_t num_interfaces;
	uint32_t num_traits;

	/* class_entry or string(s) depending on ZEND_ACC_LINKED */
	union {
		zend_class_entry **interfaces;
		zend_class_name *interface_names;
	};

	zend_class_name *trait_names;
	zend_trait_alias **trait_aliases;
	zend_trait_precedence **trait_precedences;

	union {
		struct {
			zend_string *filename;
			uint32_t line_start;
			uint32_t line_end;
			zend_string *doc_comment;
		} user;
		struct {
			const struct _zend_function_entry *builtin_functions;
			struct _zend_module_entry *module;
		} internal;
	} info;
};

typedef struct _zend_arg_info {
	zend_string *name;
	zend_type type;
	zend_uchar pass_by_reference;
	zend_bool is_variadic;
} zend_arg_info;

typedef struct _zend_internal_function {
	/* Common elements */
	zend_uchar type;
	zend_uchar arg_flags[3]; /* bitset of arg_info.pass_by_reference */
	uint32_t fn_flags;
	zend_string* function_name;
	zend_class_entry *scope;
	zend_function *prototype;
	uint32_t num_args;
	uint32_t required_num_args;
	zend_internal_arg_info *arg_info;
	/* END of common elements */

	zif_handler handler;
	struct _zend_module_entry *module;
	void *reserved[ZEND_MAX_RESERVED_RESOURCES];
} zend_internal_function;

typedef union _znode_op {
	uint32_t      constant;
	uint32_t      var;
	uint32_t      num;
	uint32_t      opline_num; /*  Needs to be signed */
#ifdef PLATFORM_32
	zend_op       *jmp_addr;
#else
	uint32_t      jmp_offset;
#endif
#ifdef PLATFORM_32
	zval          *zv;
#endif
} znode_op;

struct _zend_op {
	const void *handler;
	znode_op op1;
	znode_op op2;
	znode_op result;
	uint32_t extended_value;
	uint32_t lineno;
	zend_uchar opcode;
	zend_uchar op1_type;
	zend_uchar op2_type;
	zend_uchar result_type;
};

typedef struct _zend_live_range {
	uint32_t var; /* low bits are used for variable type (ZEND_LIVE_* macros) */
	uint32_t start;
	uint32_t end;
} zend_live_range;
typedef struct _zend_try_catch_element {
	uint32_t try_op;
	uint32_t catch_op;  /* ketchup! */
	uint32_t finally_op;
	uint32_t finally_end;
} zend_try_catch_element;
struct _zend_op_array {
	/* Common elements */
	zend_uchar type;
	zend_uchar arg_flags[3]; /* bitset of arg_info.pass_by_reference */
	uint32_t fn_flags;
	zend_string *function_name;
	zend_class_entry *scope;
	zend_function *prototype;
	uint32_t num_args;
	uint32_t required_num_args;
	zend_arg_info *arg_info;
	/* END of common elements */

	int cache_size;     /* number of run_time_cache_slots * sizeof(void*) */
	int last_var;       /* number of CV variables */
	uint32_t T;         /* number of temporary variables */
	uint32_t last;      /* number of opcodes */

	zend_op *opcodes;
	void ***run_time_cache;
	HashTable ** static_variables_ptr;
	HashTable *static_variables;
	zend_string **vars; /* names of CV variables */

	uint32_t *refcount;

	int last_live_range;
	int last_try_catch;
	zend_live_range *live_range;
	zend_try_catch_element *try_catch_array;

	zend_string *filename;
	uint32_t line_start;
	uint32_t line_end;
	zend_string *doc_comment;

	int last_literal;
	zval *literals;

	void *reserved[ZEND_MAX_RESERVED_RESOURCES];
};

union _zend_function {
	zend_uchar type;	/* MUST be the first element of this struct! */
	uint32_t   quick_arg_flags;

	struct {
		zend_uchar type;  /* never used */
		zend_uchar arg_flags[3]; /* bitset of arg_info.pass_by_reference */
		uint32_t fn_flags;
		zend_string *function_name;
		zend_class_entry *scope;
		zend_function *prototype;
		uint32_t num_args;
		uint32_t required_num_args;
		zend_arg_info *arg_info;
	} common;

	zend_op_array op_array;
	zend_internal_function internal_function;
};

typedef void (*zend_object_free_obj_t)(zend_object *object);
typedef void (*zend_object_dtor_obj_t)(zend_object *object);
typedef zend_object* (*zend_object_clone_obj_t)(zval *object);
typedef zval *(*zend_object_read_property_t)(zval *object, zval *member, int type, void **cache_slot, zval *rv);
typedef zval *(*zend_object_write_property_t)(zval *object, zval *member, zval *value, void **cache_slot);
typedef zval *(*zend_object_read_dimension_t)(zval *object, zval *offset, int type, zval *rv);
typedef void (*zend_object_write_dimension_t)(zval *object, zval *offset, zval *value);
typedef zval *(*zend_object_get_property_ptr_ptr_t)(zval *object, zval *member, int type, void **cache_slot);
typedef zval* (*zend_object_get_t)(zval *object, zval *rv);
typedef void (*zend_object_set_t)(zval *object, zval *value);
typedef int (*zend_object_has_property_t)(zval *object, zval *member, int has_set_exists, void **cache_slot);
typedef void (*zend_object_unset_property_t)(zval *object, zval *member, void **cache_slot);
typedef int (*zend_object_has_dimension_t)(zval *object, zval *member, int check_empty);
typedef void (*zend_object_unset_dimension_t)(zval *object, zval *offset);
typedef HashTable *(*zend_object_get_properties_t)(zval *object);
typedef zend_function *(*zend_object_get_method_t)(zend_object **object, zend_string *method, const zval *key);
typedef int (*zend_object_call_method_t)(zend_string *method, zend_object *object, zend_execute_data *execute_data, zval *return_value);
typedef zend_function *(*zend_object_get_constructor_t)(zend_object *object);
typedef zend_string *(*zend_object_get_class_name_t)(const zend_object *object);
typedef int (*zend_object_compare_t)(zval *object1, zval *object2);
typedef int (*zend_object_cast_t)(zval *readobj, zval *retval, int type);
typedef int (*zend_object_count_elements_t)(zval *object, zend_long *count);
typedef HashTable *(*zend_object_get_debug_info_t)(zval *object, int *is_temp);
typedef int (*zend_object_get_closure_t)(zval *obj, zend_class_entry **ce_ptr, zend_function **fptr_ptr, zend_object **obj_ptr);
typedef HashTable *(*zend_object_get_gc_t)(zval *object, zval **table, int *n);
typedef int (*zend_object_do_operation_t)(zend_uchar opcode, zval *result, zval *op1, zval *op2);
typedef int (*zend_object_compare_zvals_t)(zval *result, zval *op1, zval *op2);
typedef enum _zend_prop_purpose {
	/* Used for debugging. Supersedes get_debug_info handler. */
	ZEND_PROP_PURPOSE_DEBUG,
	/* Used for (array) casts. */
	ZEND_PROP_PURPOSE_ARRAY_CAST,
	/* Used for serialization using the "O" scheme.
	 * Unserialization will use __wakeup(). */
	ZEND_PROP_PURPOSE_SERIALIZE,
	/* Used for var_export().
	 * The data will be passed to __set_state() when evaluated. */
	ZEND_PROP_PURPOSE_VAR_EXPORT,
	/* Used for json_encode(). */
	ZEND_PROP_PURPOSE_JSON,
	/* array_key_exists(). Not intended for general use! */
	_ZEND_PROP_PURPOSE_ARRAY_KEY_EXISTS,
	/* Dummy member to ensure that "default" is specified. */
	_ZEND_PROP_PURPOSE_NON_EXHAUSTIVE_ENUM
} zend_prop_purpose;
typedef zend_array *(*zend_object_get_properties_for_t)(zval *object, zend_prop_purpose purpose);

struct _zend_object_handlers {
	/* offset of real object header (usually zero) */
	int										offset;
	/* object handlers */
	zend_object_free_obj_t					free_obj;             /* required */
	zend_object_dtor_obj_t					dtor_obj;             /* required */
	zend_object_clone_obj_t					clone_obj;            /* optional */
	zend_object_read_property_t				read_property;        /* required */
	zend_object_write_property_t			write_property;       /* required */
	zend_object_read_dimension_t			read_dimension;       /* required */
	zend_object_write_dimension_t			write_dimension;      /* required */
	zend_object_get_property_ptr_ptr_t		get_property_ptr_ptr; /* required */
	zend_object_get_t						get;                  /* optional */
	zend_object_set_t						set;                  /* optional */
	zend_object_has_property_t				has_property;         /* required */
	zend_object_unset_property_t			unset_property;       /* required */
	zend_object_has_dimension_t				has_dimension;        /* required */
	zend_object_unset_dimension_t			unset_dimension;      /* required */
	zend_object_get_properties_t			get_properties;       /* required */
	zend_object_get_method_t				get_method;           /* required */
	zend_object_call_method_t				call_method;          /* optional */
	zend_object_get_constructor_t			get_constructor;      /* required */
	zend_object_get_class_name_t			get_class_name;       /* required */
	zend_object_compare_t					compare_objects;      /* optional */
	zend_object_cast_t						cast_object;          /* optional */
	zend_object_count_elements_t			count_elements;       /* optional */
	zend_object_get_debug_info_t			get_debug_info;       /* optional */
	zend_object_get_closure_t				get_closure;          /* optional */
	zend_object_get_gc_t					get_gc;               /* required */
	zend_object_do_operation_t				do_operation;         /* optional */
	zend_object_compare_zvals_t				compare;              /* optional */
	zend_object_get_properties_for_t		get_properties_for;   /* optional */
};

struct _zend_resource {
	zend_refcounted_h gc;
	int               handle; // TODO: may be removed ???
	int               type;
	void             *ptr;
};

typedef union {
	struct _zend_property_info *ptr;
	uintptr_t list;
} zend_property_info_source_list;

struct _zend_reference {
	zend_refcounted_h              gc;
	zval                           val;
	zend_property_info_source_list sources;
};

struct _zend_ast_ref {
	zend_refcounted_h gc;
	/*zend_ast        ast; zend_ast follows the zend_ast_ref structure */
};

typedef struct _zend_closure {
	zend_object       std;
	zend_function     func;
	zval              this_ptr;
	zend_class_entry *called_scope;
	zif_handler       orig_internal_handler;
} zend_closure;

typedef enum {
	EH_NORMAL = 0,
	EH_THROW
} zend_error_handling_t;

typedef uint32_t HashPosition;

typedef struct _HashTableIterator {
	HashTable    *ht;
	HashPosition  pos;
} HashTableIterator;

typedef struct _zend_objects_store {
	zend_object **object_buckets;
	uint32_t top;
	uint32_t size;
	int free_list_head;
} zend_objects_store;

typedef struct _zend_stack {
	int size, top, max;
	void *elements;
} zend_stack;

typedef struct _zend_vm_stack *zend_vm_stack;
struct _zend_vm_stack {
	zval *top;
	zval *end;
	zend_vm_stack prev;
};

#ifdef ZEND_WIN32
typedef struct _OSVERSIONINFOEX {
  unsigned long dwOSVersionInfoSize;
  unsigned long dwMajorVersion;
  unsigned long dwMinorVersion;
  unsigned long dwBuildNumber;
  unsigned long dwPlatformId;
  char  szCSDVersion[128];
  unsigned short  wServicePackMajor;
  unsigned short  wServicePackMinor;
  unsigned short  wSuiteMask;
  unsigned char  wProductType;
  unsigned char  wReserved;
} OSVERSIONINFOEX;
#endif

struct _zend_executor_globals {
	zval uninitialized_zval;
	zval error_zval;

	/* symbol table cache */
	zend_array *symtable_cache[32]; // SYMTABLE_CACHE_SIZE=32
	/* Pointer to one past the end of the symtable_cache */
	zend_array **symtable_cache_limit;
	/* Pointer to first unused symtable_cache slot */
	zend_array **symtable_cache_ptr;

	zend_array symbol_table;		/* main symbol table */

	HashTable included_files;	/* files already included */

	void *bailout;

	int error_reporting;
	int exit_status;

	HashTable *function_table;	/* function symbol table */
	HashTable *class_table;		/* class table */
	HashTable *zend_constants;	/* constants table */

	zval          *vm_stack_top;
	zval          *vm_stack_end;
	zend_vm_stack  vm_stack; // !!!
	size_t         vm_stack_page_size;

	struct _zend_execute_data *current_execute_data;
	zend_class_entry *fake_scope; /* used to avoid checks accessing properties */

	zend_long precision;

	int ticks_count;

	uint32_t persistent_constants_count;
	uint32_t persistent_functions_count;
	uint32_t persistent_classes_count;

	HashTable *in_autoload;
	zend_function *autoload_func;
	zend_bool full_tables_cleanup;

	/* for extended information support */
	zend_bool no_extensions;

	zend_bool vm_interrupt;
	zend_bool timed_out;
	zend_long hard_timeout;

#ifdef ZEND_WIN32
	OSVERSIONINFOEX windows_version_info;
#endif

	HashTable regular_list;
	HashTable persistent_list;

	int user_error_handler_error_reporting;
	zval user_error_handler;
	zval user_exception_handler;
	zend_stack user_error_handlers_error_reporting; // !!!
	zend_stack user_error_handlers;
	zend_stack user_exception_handlers;

	zend_error_handling_t  error_handling;
	zend_class_entry      *exception_class;

	/* timeout support */
	zend_long timeout_seconds;

	int lambda_count;

	HashTable *ini_directives;
	HashTable *modified_ini_directives;
	zend_ini_entry *error_reporting_ini_entry;

	zend_objects_store objects_store; // !!!
	zend_object *exception, *prev_exception;
	const zend_op *opline_before_exception;
	zend_op exception_op[3];

	struct _zend_module_entry *current_module;

	zend_bool active;
	zend_uchar flags;

	zend_long assertions;

	uint32_t           ht_iterators_count;     /* number of allocatd slots */
	uint32_t           ht_iterators_used;      /* number of used slots */
	HashTableIterator *ht_iterators; // !!!
	HashTableIterator  ht_iterators_slots[16];

	void *saved_fpu_cw_ptr;
#ifdef XPFPA_HAVE_CW
	unsigned int saved_fpu_cw;
#endif
	zend_function trampoline;
	zend_op       call_trampoline_op;
	zend_bool each_deprecation_thrown;
	HashTable weakrefs;
	zend_bool exception_ignore_args;
	void *reserved[ZEND_MAX_RESERVED_RESOURCES];
};

// ---------------

ZEND_API zval* ZEND_FASTCALL zend_hash_str_find(const HashTable *ht, const char *key, size_t len);
ZEND_API int ZEND_FASTCALL zend_hash_str_del(HashTable *ht, const char *key, size_t len);
ZEND_API void zend_do_inheritance_ex(zend_class_entry *ce, zend_class_entry *parent_ce, zend_bool checked);

#ifdef ZTS
ZEND_API size_t executor_globals_offset;
ZEND_API void *tsrm_get_ls_cache(void);
#else
ZEND_API zend_executor_globals executor_globals;
#endif
