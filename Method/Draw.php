<?php
namespace GDO\RandomOrg\Method;

use GDO\Form\GDT_Form;
use GDO\Form\MethodForm;
use GDO\Form\GDT_Submit;
use GDO\Form\GDT_AntiCSRF;
use GDO\DB\GDT_UInt;
use GDO\RandomOrg\Module_RandomOrg;

final class Draw extends MethodForm
{
    public function createForm(GDT_Form $form)
    {
        $form->addFields([
            GDT_UInt::make('min'),
            GDT_UInt::make('max'),
            GDT_AntiCSRF::make(),
        ]);
        $form->actions()->addField(GDT_Submit::make());
    }

    public function formValidated(GDT_Form $form)
    {
        $min = $form->getFormValue('min');
        $max = $form->getFormValue('max');
        $rand = Module_RandomOrg::instance()->rand($min, $max);
        $this->message('msg_random_org_draw', [$rand]);
        return $this->renderPage();
    }
    
}
