# Entity Inheritance Hierarchy Decomposition

## Overview

This refactoring decomposes the deep entity inheritance hierarchy into a more flexible trait-based architecture. This provides better code reusability, composition, and maintainability.

## Changes Made

### New Traits Created

1. **DBElementTrait** (`src/Entity/Base/DBElementTrait.php`)
   - Provides basic database element functionality with an ID
   - Includes `getID()` method and clone helper
   - Extracted from `AbstractDBElement`

2. **NamedElementTrait** (`src/Entity/Base/NamedElementTrait.php`)
   - Provides named element functionality (name property and methods)
   - Includes `getName()`, `setName()`, and `__toString()` methods
   - Extracted from `AbstractNamedDBElement`

3. **AttachmentsTrait** (`src/Entity/Base/AttachmentsTrait.php`)
   - Provides attachments collection functionality
   - Includes methods for adding, removing, and getting attachments
   - Includes clone helper for deep cloning attachments
   - Extracted from `AttachmentContainingDBElement`

4. **StructuralElementTrait** (`src/Entity/Base/StructuralElementTrait.php`)
   - Provides tree/hierarchy functionality for structural elements
   - Includes parent/child relationships, path calculations, level tracking
   - Includes methods like `isRoot()`, `isChildOf()`, `getFullPath()`, etc.
   - Extracted from `AbstractStructuralDBElement`

5. **CompanyTrait** (`src/Entity/Base/CompanyTrait.php`)
   - Provides company-specific fields (address, phone, email, website, etc.)
   - Includes getters and setters for all company fields
   - Extracted from `AbstractCompany`

### New Interfaces Created

1. **DBElementInterface** (`src/Entity/Contracts/DBElementInterface.php`)
   - Interface for entities with a database ID
   - Defines `getID()` method

2. **StructuralElementInterface** (`src/Entity/Contracts/StructuralElementInterface.php`)
   - Interface for structural/hierarchical elements
   - Defines methods for tree navigation and hierarchy

3. **CompanyInterface** (`src/Entity/Contracts/CompanyInterface.php`)
   - Interface for company entities
   - Defines basic company information accessors

4. **HasParametersInterface** (`src/Entity/Contracts/HasParametersInterface.php`)
   - Interface for entities that have parameters
   - Defines `getParameters()` method

### Refactored Classes

1. **AbstractDBElement**
   - Now uses `DBElementTrait`
   - Implements `DBElementInterface`
   - Simplified to just use the trait instead of duplicating code

2. **AbstractNamedDBElement**
   - Now uses `NamedElementTrait` in addition to existing `TimestampTrait`
   - Cleaner implementation with trait composition

3. **AttachmentContainingDBElement**
   - Now uses `AttachmentsTrait` and `MasterAttachmentTrait`
   - Simplified constructor and clone methods

4. **AbstractStructuralDBElement**
   - Now uses `StructuralElementTrait` and `ParametersTrait`
   - Implements `StructuralElementInterface` and `HasParametersInterface`
   - Much cleaner with most functionality extracted to trait

5. **AbstractCompany**
   - Now uses `CompanyTrait`
   - Implements `CompanyInterface`
   - Significantly simplified from ~260 lines to ~20 lines

## Benefits

### 1. **Better Code Reusability**
   - Traits can be reused in different contexts without requiring inheritance
   - Easier to mix and match functionality

### 2. **Improved Maintainability**
   - Each trait focuses on a single concern (SRP - Single Responsibility Principle)
   - Easier to locate and modify specific functionality
   - Reduced code duplication

### 3. **More Flexible Architecture**
   - Entities can now compose functionality as needed
   - Not locked into a rigid inheritance hierarchy
   - Easier to add new functionality without modifying base classes

### 4. **Better Testability**
   - Traits can be tested independently
   - Easier to mock specific functionality

### 5. **Clearer Contracts**
   - Interfaces make dependencies explicit
   - Better IDE support and type hinting

## Migration Path

This refactoring is backward compatible - all existing entities continue to work as before. The changes are internal to the base classes and do not affect the public API.

### For New Entities

New entities can now:
1. Use traits directly without deep inheritance
2. Mix and match functionality as needed
3. Implement only the interfaces they need

Example:
```php
class MyCustomEntity extends AbstractDBElement implements NamedElementInterface 
{
    use NamedElementTrait;
    
    // Custom functionality
}
```

## Technical Details

### Trait Usage Pattern

All traits follow this pattern:
1. Declare properties with appropriate Doctrine/validation annotations
2. Provide initialization methods (e.g., `initializeAttachments()`)
3. Provide business logic methods
4. Provide clone helpers for deep cloning when needed

### Interface Contracts

All interfaces define the minimal contract required for that functionality:
- DBElementInterface: requires `getID()`
- NamedElementInterface: requires `getName()`
- StructuralElementInterface: requires hierarchy methods
- CompanyInterface: requires company info accessors
- HasParametersInterface: requires `getParameters()`

## Future Improvements

Potential future enhancements:
1. Extract more functionality from remaining abstract classes
2. Create more granular traits for specific features
3. Add trait-specific unit tests
4. Consider creating trait-based mixins for common entity patterns
