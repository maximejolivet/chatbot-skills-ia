<skills_system priority="1" schema-version="2">

## Available Skills

<!-- SKILLS_TABLE_START -->
<usage>
When users ask you to perform tasks, check if any of the available skills below can help complete the task more effectively. Skills provide specialized capabilities and domain knowledge.

How to use skills:
- Invoke: Bash("composer read-skill <skill-name>")
- The skill content will load with detailed instructions on how to complete the task
- IMPORTANT: Always cd to the Base Directory shown in output before executing scripts or accessing bundled resources

Usage notes:
- For project-specific tasks, only use skills listed in <available_skills> below
- Note: Native capabilities (e.g., via the Skill tool) remain available alongside project skills
- Do not invoke a skill that is already loaded in your context
- Each skill invocation is stateless
</usage>

<available_skills>

<skill>
<name>php-modernization</name>
<description>Use when modernizing PHP code: PHP 8.1-8.5 features, PSR/PHP-FIG/PER-CS compliance, PHPStan/Rector/PHP-CS-Fixer/PHPat tooling, DTOs/enums/readonly/property hooks, type safety. Triggers: PHP modernization, type safety, PHPStan, Rector, PHP-CS-Fixer, enum, DTO, readonly, strict_types, property hooks, PHP 8.4, PHP 8.5.</description>
<location>/app/vendor/agent-skills/installed/php-modernization</location>
<source>direct:netresearch/php-modernization-skill</source>
<pin>main@21d01a98</pin>
</skill>

</available_skills>
<!-- SKILLS_TABLE_END -->

</skills_system>
