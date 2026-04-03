<?php

namespace Biollante\Traits;

use OwenIt\Auditing\Exceptions\AuditingException;
use Illuminate\Support\Facades\Config;

trait CustomAuditable
{
	use \OwenIt\Auditing\Auditable;

	public function toAudit(): array
	{
		if (!$this->readyForAuditing()) {
			throw new AuditingException('A valid audit event has not been set');
		}

		$attributeGetter = $this->resolveAttributeGetter($this->auditEvent);

		if (!method_exists($this, $attributeGetter)) {
			throw new AuditingException(sprintf(
				'Unable to handle "%s" event, %s() method missing',
				$this->auditEvent,
				$attributeGetter
			));
		}

		$this->resolveAuditExclusions();

		list($old, $new) = $this->$attributeGetter();

		if ($this->getAttributeModifiers() && !$this->isCustomEvent) {
			foreach ($old as $attribute => $value) {
				$old[$attribute] = $this->modifyAttributeValue($attribute, $value);
			}

			foreach ($new as $attribute => $value) {
				$new[$attribute] = $this->modifyAttributeValue($attribute, $value);
			}
		}

		$morphPrefix = Config::get('audit.user.morph_prefix', 'user');

		$tags = implode(',', $this->generateTags());

		$user = $this->resolveUser();

		return $this->transformAudit(array_merge([
			'old_values'           => $old,
			'new_values'           => $new,
			'event'                => $this->auditEvent,
			'auditable_id'         => $this->getKey(),
			'auditable_type'       => $this->getMorphClass(),
			$morphPrefix . '_id'   => $user ? $user->getAuthIdentifier() : 1,
			'role'                 => $user ? $user->getMorphClass() : 'Visitor',
			'tags'                 => empty($tags) ? null : $tags,
			'created_by'           => $user ? $user->id : 1
		], $this->runResolvers()));
	}
}
