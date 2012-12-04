<?php
require_once('deadline://thirdparty/rb.php');

class ModelFormatter implements RedBean_IModelFormatter {
	public function formatModel($model) { return 'Deadline\\' . $model; }
}
RedBean_ModelHelper::setModelFormatter(new ModelFormatter());

?>
