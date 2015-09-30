<?php
namespace KG\BeekeepingManagementBundle\Controller;
use KG\BeekeepingManagementBundle\Entity\RecolteRucher;
use KG\BeekeepingManagementBundle\Entity\RecolteRuche;
use KG\BeekeepingManagementBundle\Entity\Rucher;
use KG\BeekeepingManagementBundle\Form\Type\RecolteRucherType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;

class RecolteRucherController extends Controller
{   
    /**
    * @Security("has_role('ROLE_USER')")
    * @ParamConverter("rucher", options={"mapping": {"rucher_id" : "id"}})  
    */    
    public function viewAllAction(Rucher $rucher, $page)
    {
        $exploitation = $rucher->getExploitation();
        $apiculteurExploitations = $exploitation->getApiculteurExploitations();
        $not_permitted = true;
        
        foreach ( $apiculteurExploitations as $apiculteurExploitation ){
            if( $apiculteurExploitation->getApiculteur()->getId() == $this->getUser()->getId() ){
                $not_permitted = false;
                break;
            }
        }
        
        if( $not_permitted || $page < 1  || $rucher->getRecoltesrucher()->isEmpty()){
            throw new NotFoundHttpException('Page inexistante.');
        }
 
        $maxRecoltes    = $this->container->getParameter('max_recoltes_per_page');
        $recoltes       = $this->getDoctrine()->getRepository('KGBeekeepingManagementBundle:RecolteRucher')->getListByRucher($page, $maxRecoltes, $rucher->getId());
        $recoltes_count = $this->getDoctrine()->getRepository('KGBeekeepingManagementBundle:RecolteRucher')->countByRucher($rucher->getId()); 
        $pagination = array(
            'page'         => $page,
            'route'        => 'kg_beekeeping_management_view_recoltes',
            'pages_count'  => max ( ceil($recoltes_count / $maxRecoltes), 1),
            'route_params' => array('rucher_id' => $rucher->getId())
        );
        
        return $this->render('KGBeekeepingManagementBundle:RecolteRucher:viewAll.html.twig', 
                array(  'rucher'     => $rucher,
                        'recoltes'   => $recoltes,
                        'nbRecoltes' => $recoltes_count,
                        'pagination' => $pagination));
    }
    
    /**
    * @Security("has_role('ROLE_USER')")
    * @ParamConverter("rucher", options={"mapping": {"rucher_id" : "id"}})  
    */    
    public function addAction(Rucher $rucher, Request $request)
    {
        $exploitation = $rucher->getExploitation();
        $apiculteurExploitations = $exploitation->getApiculteurExploitations();
        $not_permitted = true;
        
        foreach ( $apiculteurExploitations as $apiculteurExploitation ){
            if( $apiculteurExploitation->getApiculteur()->getId() == $this->getUser()->getId() ){
                $not_permitted = false;
                break;
            }
        }

        if( $not_permitted ){
            throw new NotFoundHttpException('Page inexistante.');
        }       
               
        $recolterucher = new RecolteRucher();
        $recolterucher->setRucher($rucher);

        $form = $this->createForm(new RecolteRucherType( $this->getDoctrine()->getManager() ), $recolterucher);
        
        if ($form->handleRequest($request)->isValid()){
                
            $em = $this->getDoctrine()->getManager();
            
            $lastRecolte   = $rucher->getRecoltesrucher()->last();      
            if ( $lastRecolte ){
                if ( $lastRecolte->getDate() == $recolterucher->getDate() ){
                    $recolterucher = $lastRecolte;
                }
            } 
            
            foreach( $form->get('ruches')->getData() as $ruche){
                $recolteruche = new RecolteRuche( $ruche, $recolterucher);
                
                if( !$recolteruche->getHausses()->isEmpty() ){
                    $recolterucher->addRecoltesruche($recolteruche);
                }
                
                foreach ( $ruche->getHausses() as $hausse ){
                    if( $hausse->getNbPlein() <= 0 ){
                        $em->remove($hausse);
                    }
                }
            }

            $em->persist($recolterucher);
            $em->flush();
        
            $request->getSession()->getFlashBag()->add('success','Récolte créée avec succès');
        
            return $this->redirect($this->generateUrl('kg_beekeeping_management_view_rucher', array('rucher_id' => $rucher->getId())));
        }
        return $this->render('KGBeekeepingManagementBundle:RecolteRucher:add.html.twig', 
                             array(
                                    'form'    => $form->createView(),
                                    'rucher' => $rucher
                ));        
    }
}